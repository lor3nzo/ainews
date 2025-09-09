<?php
// public_html/ainews/cron/fetch.php
declare(strict_types=1);

/**
 * Cron ingester (DB-less).
 * Modes:
 *  - original (default): broad HN query
 *  - simple: HN query limited to "AI OR LLM OR ChatGPT"
 * Switching here only affects the HN search query. RSS is always ingested; filtering is done in API.
 */

ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__ . '/../data/php-errors.log');
error_reporting(E_ALL);

/* ==== mode ==== */
const MODE = 'original'; // change to 'simple' if you want HN fetch narrower

/* ==== paths ==== */
const ROOT_DIR      = __DIR__ . '/..';
const DATA_DIR      = ROOT_DIR . '/data';
const MANIFEST_DIR  = ROOT_DIR . '/manifests';
const MANIFEST_FILE = MANIFEST_DIR . '/daily.json';
const TMP_DIR       = ROOT_DIR . '/tmp';

/* ==== ensure dirs ==== */
foreach ([DATA_DIR, MANIFEST_DIR, TMP_DIR] as $dir) {
  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    error_log("Cannot create dir: $dir");
    http_response_code(500);
    echo "ERR: cannot create $dir\n";
    exit(1);
  }
}

/* ==== sources ==== */
$SOURCES = [
  'hn'          => 'Hacker News',
  'techcrunch'  => 'TechCrunch',
  'mashable'    => 'Mashable',
  'theverge'    => 'The Verge',
  'techradar'   => 'TechRadar',
  'techmeme'    => 'Techmeme',
  'arstechnica' => 'Ars Technica',
];
$RSS = [
  'techcrunch'  => 'https://techcrunch.com/feed/',
  'mashable'    => 'https://mashable.com/feeds/rss/',
  'theverge'    => 'https://www.theverge.com/rss/index.xml',
  'techradar'   => 'https://www.techradar.com/rss',
  'techmeme'    => 'https://www.techmeme.com/feed.xml',
  'arstechnica' => 'https://feeds.arstechnica.com/arstechnica/index',
];

/* ==== HN query (depends on MODE) ==== */
$HN_QUERY = urlencode(
  MODE === 'simple'
  ? 'ai OR llm OR chatgpt'
  : 'ai OR "artificial intelligence" OR "machine learning" OR ml OR llm OR "large language model" OR gpt OR chatgpt OR multimodal OR transformer OR "fine-tuning" OR inference OR prompt OR agent OR rag OR "deep learning" OR "neural network" OR "ai-powered" OR automation OR autonomous OR robotics OR "generative ai"'
);

/* ==== lookback ==== */
$LOOKBACK = 24 * 3600;

/* ==== scoring (kept for completeness; API will filter anyway) ==== */
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

/* ==== helpers ==== */
function http_get(string $url, int $timeout = 15): ?string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_USERAGENT => 'osgk-ainews-fetcher/1.2 (+https://osgk.com/ainews)',
    CURLOPT_ENCODING => '',
  ]);
  $res = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if ($res === false || $status >= 400) { error_log("GET fail [$status] $url :: $err"); return null; }
  return $res;
}
function strip_utm(string $url): string {
  $p = parse_url($url);
  if (!$p || empty($p['host'])) return $url;
  $qs = [];
  if (!empty($p['query'])) {
    parse_str($p['query'], $qs);
    foreach (array_keys($qs) as $k) {
      if (str_starts_with($k, 'utm_') || in_array($k, ['fbclid','gclid','mc_cid','mc_eid'])) unset($qs[$k]);
    }
  }
  $scheme = $p['scheme'] ?? 'https';
  $port = isset($p['port']) ? ':' . $p['port'] : '';
  $path = $p['path'] ?? '/';
  $query = $qs ? '?' . http_build_query($qs) : '';
  $frag = isset($p['fragment']) ? '#' . $p['fragment'] : '';
  return "{$scheme}://{$p['host']}{$port}{$path}{$query}{$frag}";
}
function url_hash(string $url): string { return sha1(mb_strtolower($url, 'UTF-8')); }
function ai_score(string $title, string $summary = ''): float {
  global $AI_ENTITIES, $AI_TERMS;
  $t = mb_strtolower($title . ' ' . $summary, 'UTF-8');
  $s = 0.0;
  foreach ($AI_ENTITIES as $e) if (str_contains($t, $e)) $s += 2.0;
  foreach ($AI_TERMS as $w)    if (str_contains($t, $w)) $s += 1.0;
  if (str_contains($t, 'ai') && str_contains($t, 'model')) $s += 0.5;
  return $s;
}
function ensure_dir_for(string $filePath): void { $dir = dirname($filePath); if (!is_dir($dir)) mkdir($dir, 0775, true); }
function append_jsonl(string $file, array $row): void {
  ensure_dir_for($file);
  $fh = fopen($file, 'ab'); if (!$fh) { error_log("Cannot open shard for append: $file"); return; }
  fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES) . "\n"); fclose($fh);
}
function load_seen_set(string $path): array {
  if (!is_file($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  $s = []; foreach ($lines as $h) $s[$h] = true; return $s;
}
function save_seen_set(string $path, array $set): void { ensure_dir_for($path); file_put_contents($path, implode("\n", array_keys($set))); }
function update_manifest(string $todayShardRel): void {
  $list = [];
  if (is_file(MANIFEST_FILE)) { $cur = json_decode((string)file_get_contents(MANIFEST_FILE), true); if (is_array($cur)) $list = $cur; }
  if (!in_array($todayShardRel, $list, true)) array_unshift($list, $todayShardRel);
  $list = array_slice($list, 0, 14);
  file_put_contents(MANIFEST_FILE, json_encode($list, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
function relativeToAinewsRoot(string $abs): string {
  $root = realpath(ROOT_DIR);
  $a = str_replace('\\','/',$abs); $r = str_replace('\\','/',$root);
  if ($root && str_starts_with($a, $r)) return ltrim(substr($a, strlen($r)), '/');
  return $abs;
}

/* ==== main ==== */
$now = time();
$sinceUnix = $now - $LOOKBACK;

$today = gmdate('Y/m/d');
$shardAbs = DATA_DIR . '/' . $today . '.jsonl';
$shardRel = 'data/' . $today . '.jsonl';

$seenFile = TMP_DIR . '/seen-' . gmdate('Ymd') . '.txt';
$seen = load_seen_set($seenFile);

$added = 0;

/* -- HN -- */
$hnUrl = "https://hn.algolia.com/api/v1/search_by_date?query={$HN_QUERY}&tags=story&numericFilters=created_at_i>{$sinceUnix}&hitsPerPage=100";
if ($json = http_get($hnUrl)) {
  $obj = json_decode($json, true);
  if (isset($obj['hits']) && is_array($obj['hits'])) {
    foreach ($obj['hits'] as $hit) {
      $title = (string)($hit['title'] ?? '');
      $url   = (string)($hit['url'] ?? '');
      $id    = (string)($hit['objectID'] ?? '');
      $ts    = (int)($hit['created_at_i'] ?? 0);
      if ($title === '' || $id === '' || $ts === 0) continue;

      $comments = "https://news.ycombinator.com/item?id=" . rawurlencode($id);
      $finalUrl = $url ? strip_utm($url) : $comments;
      $hash = url_hash($finalUrl);
      if (isset($seen[$hash])) continue;

      $row = [
        'source'       => 'hn',
        'title'        => $title,
        'url'          => $finalUrl,
        'comments_url' => $comments,
        'author'       => (string)($hit['author'] ?? null),
        'published_at' => gmdate('c', $ts),
      ];
      $row['ai_score'] = ai_score($row['title']);
      $seen[$hash] = true;
      append_jsonl($shardAbs, $row);
      $added++;
    }
  }
}

/* -- RSS -- */
foreach ($RSS as $sid => $feedUrl) {
  $xmlStr = http_get($feedUrl); if (!$xmlStr) continue;
  libxml_use_internal_errors(true);
  $xml = simplexml_load_string($xmlStr);
  if (!$xml) { error_log("XML parse failed: $feedUrl"); continue; }

  $items = [];
  if (isset($xml->channel->item)) {
    foreach ($xml->channel->item as $it) {
      $items[] = [
        'title'   => (string)$it->title,
        'url'     => (string)$it->link,
        'date'    => (string)$it->pubDate,
        'author'  => (string)($it->author ?? ''),
        'summary' => (string)($it->description ?? ''),
      ];
    }
  } elseif (isset($xml->entry)) {
    foreach ($xml->entry as $it) {
      $link = '';
      foreach ($it->link as $lnk) {
        $attrs = $lnk->attributes(); $rel = (string)($attrs['rel'] ?? ''); $href = (string)($attrs['href'] ?? '');
        if ($rel === '' || $rel === 'alternate') { $link = $href; break; }
      }
      if ($link === '' && isset($it->link['href'])) $link = (string)$it->link['href'];
      $date = (string)($it->updated ?? $it->published ?? '');
      $items[] = [
        'title'   => (string)$it->title,
        'url'     => $link,
        'date'    => $date,
        'author'  => (string)($it->author->name ?? ''),
        'summary' => (string)($it->summary ?? $it->content ?? ''),
      ];
    }
  } else { continue; }

  foreach ($items as $it) {
    $title = trim($it['title'] ?? '');
    $url   = trim($it['url']   ?? '');
    if ($title === '' || $url === '') continue;

    $finalUrl = strip_utm($url);
    $ts = 0; if (!empty($it['date'])) { $ts = strtotime((string)$it['date']); if ($ts === false) $ts = 0; }
    if ($ts === 0) $ts = $now;
    if ($ts < $sinceUnix) continue;

    $hash = url_hash($finalUrl);
    if (isset($seen[$hash])) continue;

    $row = [
      'source'       => $sid,
      'title'        => $title,
      'url'          => $finalUrl,
      'author'       => ($it['author'] ?? '') ?: null,
      'published_at' => gmdate('c', $ts),
      'summary'      => trim(strip_tags((string)($it['summary'] ?? ''))),
    ];
    $row['ai_score'] = ai_score($row['title'], (string)($row['summary'] ?? ''));

    $seen[$hash] = true;
    append_jsonl($shardAbs, $row);
    $added++;
  }
}

/* ==== save & manifest ==== */
save_seen_set($seenFile, $seen);
update_manifest(relativeToAinewsRoot($shardAbs));

/* ==== output ==== */
header('Content-Type: text/plain; charset=utf-8');
echo "OK - added {$added} items into {$shardRel} (mode=" . MODE . ")\n";
