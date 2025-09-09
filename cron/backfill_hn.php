<?php
// public_html/ainews/cron/backfill_hn.php
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__ . '/../data/php-errors.log');
error_reporting(E_ALL);

const ROOT_DIR = __DIR__ . '/..';
const DATA_DIR = ROOT_DIR . '/data';
const TMP_DIR  = ROOT_DIR . '/tmp';

$DAYS = isset($_GET['days']) ? max(1, min(365, (int)$_GET['days'])) : 30; // default 30 days
$QUERY = urlencode('ai OR llm OR chatgpt OR "artificial intelligence" OR "large language model" OR gpt');

function http_get(string $url, int $timeout=20): ?string {
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'osgk-ainews-backfill/1.0',CURLOPT_ENCODING=>'']);
  $res=curl_exec($ch); $st=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  if($res===false||$st>=400){ error_log("GET fail [$st] $url :: $err"); return null; }
  return $res;
}
function ensure(string $p){ if(!is_dir($p)) mkdir($p,0775,true); }
function append_jsonl(string $file, array $row): void { ensure(dirname($file)); $fh=fopen($file,'ab'); if(!$fh){error_log("open fail $file"); return;} fwrite($fh,json_encode($row,JSON_UNESCAPED_SLASHES)."\n"); fclose($fh); }
function strip_utm(string $url): string {
  $p=parse_url($url); if(!$p||empty($p['host'])) return $url;
  $qs=[]; if(!empty($p['query'])){ parse_str($p['query'],$qs); foreach(array_keys($qs) as $k){ if(str_starts_with($k,'utm_')||in_array($k,['fbclid','gclid','mc_cid','mc_eid'])) unset($qs[$k]); } }
  $scheme=$p['scheme']??'https'; $port=isset($p['port'])?':'.$p['port']:''; $path=$p['path']??'/'; $query=$qs?'?'.http_build_query($qs):''; $frag=isset($p['fragment'])?'#'.$p['fragment']:'';
  return "{$scheme}://{$p['host']}{$port}{$path}{$query}{$frag}";
}

$now=new DateTimeImmutable('now', new DateTimeZone('UTC'));
$total=0;

for($i=0;$i<$DAYS;$i++){
  $day = $now->modify("-$i days");
  $start = (new DateTimeImmutable($day->format('Y-m-d').' 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
  $end   = (new DateTimeImmutable($day->format('Y-m-d').' 23:59:59', new DateTimeZone('UTC')))->getTimestamp();

  $page=0; $hits=0;
  do {
    $url="https://hn.algolia.com/api/v1/search_by_date?query={$QUERY}&tags=story&numericFilters=created_at_i>={$start},created_at_i<={$end}&hitsPerPage=100&page={$page}";
    $json=http_get($url); if(!$json) break;
    $obj=json_decode($json,true);
    $arr=$obj['hits']??[]; $nb=$obj['nbPages']??1;

    foreach($arr as $hit){
      $title=(string)($hit['title']??''); $url=(string)($hit['url']??''); $id=(string)($hit['objectID']??''); $ts=(int)($hit['created_at_i']??0);
      if($title===''||$id===''||$ts===0) continue;
      $comments="https://news.ycombinator.com/item?id=".rawurlencode($id);
      $final=$url?strip_utm($url):$comments;

      $row=['source'=>'hn','title'=>$title,'url'=>$final,'comments_url'=>$comments,'author'=>(string)($hit['author']??null),'published_at'=>gmdate('c',$ts)];
      $shard=DATA_DIR.'/'.$day->format('Y/m/d').'.jsonl';
      append_jsonl($shard,$row); $total++;
    }
    $page++;
  } while ($page < ($nb??1));
}

header('Content-Type:text/plain; charset=utf-8');
echo "HN backfill complete: {$total} items across {$DAYS} day(s)\n";
