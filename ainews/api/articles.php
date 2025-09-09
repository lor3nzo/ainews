<?php
// public_html/ainews/api/articles.php
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__ . '/../data/php-errors.log');
error_reporting(E_ALL);

const DATA_DIR = __DIR__ . '/../data';
const MANIFEST_FILE = __DIR__ . '/../manifests/daily.json';

$ALLOWED_SOURCES = [
  'hn'=>'Hacker News','techcrunch'=>'TechCrunch','mashable'=>'Mashable',
  'theverge'=>'The Verge','techradar'=>'TechRadar','techmeme'=>'Techmeme','arstechnica'=>'Ars Technica',
];

$u = $_GET;
$mode = strtolower($u['mode'] ?? 'original'); // 'original' | 'simple'
$since = $u['since'] ?? '24h';
$sinceMap = ['24h'=>'-24 hours','7d'=>'-7 days','30d'=>'-30 days','365d'=>'-365 days'];
$sinceSpec = $sinceMap[$since] ?? $sinceMap['7d'];
$cutoffTs = (new DateTimeImmutable($sinceSpec))->getTimestamp();

$sort = $u['sort'] ?? 'published_at_desc';
$sortAsc = ($sort === 'published_at_asc');

$perPage = isset($u['per_page']) ? intval($u['per_page']) : 30;
if (!in_array($perPage,[20,30,50,100],true)) $perPage = 30;

$page = isset($u['page']) ? max(1, intval($u['page'])) : 1;

$sourcesParam = $u['sources'] ?? '';
$requestedSources = array_filter(array_map('trim', explode(',', $sourcesParam)));
$sourceFilter=[]; foreach ($requestedSources as $sid) if (isset($ALLOWED_SOURCES[$sid])) $sourceFilter[$sid]=true;
$filterBySource = count($sourceFilter)>0;

$q = trim($u['q'] ?? '');

$aiEnabled = !isset($u['ai']) || strtolower($u['ai']) !== 'off';
$AI_THRESHOLD = isset($u['ai_threshold']) ? floatval($u['ai_threshold']) : 1.0;

/* ===== original-mode keyword sets ===== */
$AI_ENTITIES = [
  'openai','anthropic','google ai','gemini','microsoft','copilot','meta ai','llama',
  'mistral','qwen','deepseek','hugging face','perplexity','x.ai','stability ai',
  'reka','runway','pika','luma','coze','samba nova','nvidia ai','intel gaudi','aws bedrock',
  'apple intelligence','cerebras','databricks','snowflake','oracle ai','ibm watsonx',
  'salesforce einstein','adobe firefly'
];
$AI_TERMS = [
  'ai','artificial intelligence','machine learning','ml','llm','large language model',
  'gpt','chatgpt','multimodal','transformer','fine-tuning','inference','prompt','agent',
  'rag','vector db','token','context window','sora','veo','sonnet','opus','command r','grok',
  'diffusion','sdxl','stable diffusion','deep learning','neural network','ai-powered','ai powered',
  'automation','autonomous','robotics','generative','genai','voice cloning','speech synthesis',
  'text-to-image','text to image','text-to-video','text to video','text-to-speech','text to speech',
  'computer vision','recommendation engine','predictive analytics'
];
function ai_score(string $title, string $summary=''): float {
  global $AI_ENTITIES,$AI_TERMS;
  $t = mb_strtolower($title.' '.$summary,'UTF-8'); $s=0.0;
  foreach($AI_ENTITIES as $e) if (str_contains($t,$e)) $s+=2.0;
  foreach($AI_TERMS as $w)    if (str_contains($t,$w)) $s+=1.0;
  if (str_contains($t,'ai') && str_contains($t,'model')) $s+=0.5;
  return $s;
}

/* ===== simple-mode matcher ===== */
function simple_match(string $title, string $summary=''): bool {
  $text=' '.$title.' '.$summary.' ';
  return (bool)preg_match('/(?<![A-Za-z0-9])(A\.?I\.?|LLMs?|ChatGPT)(?![A-Za-z0-9])/i',$text);
}

/* ===== build shard list from filesystem across cutoff → today (max 370 days) ===== */
$shards = [];
$maxDays = 370;
$start = (new DateTimeImmutable('@'.$cutoffTs))->setTimezone(new DateTimeZone('UTC'));
$end   = new DateTimeImmutable('now', new DateTimeZone('UTC'));
if ($start > $end) { $tmp=$start; $start=$end; $end=$tmp; }
$iter = $end;
$days=0;
while ($iter >= $start && $days < $maxDays) {
  $rel = 'data/'.$iter->format('Y/m/d').'.jsonl';
  $abs = __DIR__.'/../'.$rel;
  if (is_file($abs)) $shards[] = $rel;
  $iter = $iter->modify('-1 day');
  $days++;
}
// Fallback to manifest if nothing found (e.g., first day)
if (!$shards && is_file(MANIFEST_FILE)) {
  $m = json_decode((string)@file_get_contents(MANIFEST_FILE), true);
  if (is_array($m)) $shards = $m;
}
if (!$shards) {
  returnJsonCached(['items'=>[],'page'=>$page,'per_page'=>$perPage,'total'=>0,'since'=>$since,'sort'=>$sort,'sources'=>array_keys($ALLOWED_SOURCES),'ai'=>$aiEnabled?'on':'off','mode'=>$mode]);
  exit;
}

/* ===== stream & filter ===== */
$items=[]; $maxNeeded = max($page*$perPage*3, 300);
foreach ($shards as $relPath) {
  $abs = realpath(dirname(__DIR__).'/'.$relPath);
  if (!$abs || !is_file($abs)) continue;
  $fh = @fopen($abs,'rb'); if(!$fh) continue;
  while (!feof($fh)) {
    $line = fgets($fh); if ($line === false) break;
    $row = json_decode($line, true); if (!is_array($row)) continue;

    if (empty($row['source'])||empty($row['title'])||empty($row['url'])||empty($row['published_at'])) continue;
    $src = strtolower((string)$row['source']); if (!isset($ALLOWED_SOURCES[$src])) continue;
    if ($filterBySource && !isset($sourceFilter[$src])) continue;

    $ts = strtotime((string)$row['published_at']); if ($ts===false || $ts<$cutoffTs) continue;

    $title=(string)$row['title']; $summary=(string)($row['summary']??'');

    if ($mode==='simple') {
      if (!simple_match($title,$summary)) continue;
    } else {
      if ($aiEnabled) {
        $score = ai_score($title,$summary);
        if ($score < $AI_THRESHOLD) continue;
      }
    }

    if ($q!=='') {
      $hay = mb_strtolower($title.' '.$summary,'UTF-8');
      if (!str_contains($hay, mb_strtolower($q,'UTF-8'))) continue;
    }

    $items[]=[
      'source'=>$src,'source_name'=>$ALLOWED_SOURCES[$src],
      'title'=>$title,'url'=>(string)$row['url'],
      'comments_url'=>isset($row['comments_url'])?(string)$row['comments_url']:null,
      'author'=>isset($row['author'])?(string)$row['author']:null,
      'published_at'=>gmdate('c',$ts),
    ];
    if (count($items) >= $maxNeeded) break;
  }
  fclose($fh);
  if (count($items) >= $maxNeeded) break;
}

/* ===== sort & paginate ===== */
usort($items,function($a,$b) use($sortAsc){ $ta=strtotime($a['published_at']); $tb=strtotime($b['published_at']); return $sortAsc?($ta<=>$tb):($tb<=>$ta); });
$total=count($items); $offset=($page-1)*$perPage; $paginated=($offset<$total)?array_slice($items,$offset,$perPage):[];

$out=['items'=>$paginated,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'since'=>$since,'sort'=>$sort,'sources'=>array_keys($ALLOWED_SOURCES),'ai'=>$aiEnabled?'on':'off','mode'=>$mode];
returnJsonCached($out); exit;

function returnJsonCached(array $data): void {
  $body=json_encode($data, JSON_UNESCAPED_SLASHES);
  if ($body===false){ http_response_code(500); header('Content-Type: application/json'); echo '{"error":"encode_failed"}'; return; }
  $etag='"'.sha1($body).'"'; $inm=$_SERVER['HTTP_IF_NONE_MATCH']??null;
  if ($inm && $inm===$etag){ header('ETag: '.$etag); header('Cache-Control: public, s-maxage=120, stale-while-revalidate=300'); http_response_code(304); return; }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: public, s-maxage=120, stale-while-revalidate=300');
  header('ETag: '.$etag); echo $body;
}
