<?php
require_once __DIR__ . "/config.php";
header("Content-Type: application/json");

// 🔹 Logging
function writeLog($title, $data) {
    global $DEBUG_LOG_FILE;
    $time = date("Y-m-d H:i:s");
    if (is_array($data) || is_object($data)) {
        $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    file_put_contents($DEBUG_LOG_FILE, "[$time] $title\n$data\n\n", FILE_APPEND);
}

/* ===== Helper ===== */
function formatNumber($num){
    $num = preg_replace('/[^0-9\+]/','',$num);
    if ($num === '') return '';
    if (strpos($num,'+62') === 0) $num = substr($num,1);
    $num = preg_replace('/[^0-9]/','',$num);
    if ($num === '') return '';
    if (strpos($num,'0') === 0) return '62'.substr($num,1);
    if (strpos($num,'62') === 0) return $num;
    return $num;
}

/* === Rate limit === */
function checkRateLimit($ip,$key){
    global $RATE_LIMIT_FILE,$RATE_LIMIT_WINDOW,
           $RATE_LIMIT_MAX_IP,$RATE_LIMIT_MAX_NUMBER,$RATE_LIMIT_MAX_IP_WA;
    $now = time();
    $data = file_exists($RATE_LIMIT_FILE)
        ? json_decode(file_get_contents($RATE_LIMIT_FILE),true)
        : ['ip'=>[],'number'=>[],'wa'=>[]];
    foreach($data as $group=>$entries){
        foreach($entries as $k=>$arr){
            $data[$group][$k] = array_values(array_filter($arr,function($t) use ($now,$RATE_LIMIT_WINDOW){
                return $t > $now - $RATE_LIMIT_WINDOW;
            }));
            if(empty($data[$group][$k])) unset($data[$group][$k]);
        }
    }
    if($key==='wa'){
        $data['wa'][$ip][]=$now;
        if(count($data['wa'][$ip])>$RATE_LIMIT_MAX_IP_WA){
            file_put_contents($RATE_LIMIT_FILE,json_encode($data));
            return "Terlalu sering membuka WhatsApp dari IP ini.";
        }
    }else{
        $data['ip'][$ip][]=$now;
        if(count($data['ip'][$ip])>$RATE_LIMIT_MAX_IP){
            file_put_contents($RATE_LIMIT_FILE,json_encode($data));
            return "Terlalu banyak percobaan dari IP yang sama.";
        }
        $data['number'][$key][]=$now;
        if(count($data['number'][$key])>$RATE_LIMIT_MAX_NUMBER){
            file_put_contents($RATE_LIMIT_FILE,json_encode($data));
            return "Nomor ini sudah terlalu sering digunakan.";
        }
    }
    file_put_contents($RATE_LIMIT_FILE,json_encode($data));
    return null;
}

/* === Validasi Watzap === */
function validateWithWatzap($phone){
    global $WATZAP_API_KEY,$WATZAP_NUMBER_KEY;
    $payload = json_encode([
        'api_key'    => $WATZAP_API_KEY,
        'number_key' => $WATZAP_NUMBER_KEY,
        'phone_no'   => $phone
    ]);
    $ch = curl_init("https://api.watzap.id/v1/validate_number");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 8
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    writeLog("VALIDATE_RESPONSE", ["http"=>$http,"resp"=>$resp,"err"=>$err,"payload"=>$payload]);
    if($err) return [false,"Tidak bisa konek: $err",true];
    if($http === 200){
        $j = json_decode($resp,true);
        if(isset($j['status']) && ($j['status'] == "200" || $j['status'] === 200)){
            return [true,null,false];
        }
        if(isset($j['message'])){
            return [false,"Nomor WA tidak terdaftar. API: ".$j['message'],false];
        }
        return [false,"Nomor WA tidak terdaftar.",false];
    }
    if(in_array($http,[400,422])) return [false,"Nomor WA tidak terdaftar.",false];
    if(in_array($http,[401,403,404])) return [false,"Terjadi kesalahan server pada form pemesanan, gunakan tombol whatsapp alternatif dibawah ini! (HTTP $http).",true];
    if($http >= 500) return [false,"Terjadi kesalahan server pada form pemesanan, gunakan tombol whatsapp alternatif dibawah ini! (HTTP $http).",true];
    return [false,"Validasi gagal (HTTP $http).",true];
}

/* === Ambil token Loops === */
function getLoopsFormHidden($pageUrl) {
    $ch = curl_init($pageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible)'
    ]);
    $html = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    writeLog("LOOPS_TOKEN_PAGE", ["url"=>$pageUrl,"http"=>$http,"err"=>$err,"html_snippet"=>substr($html,0,800)]);
    $result = ["_token"=>null,"visitor_id"=>null,"campaign_id"=>null,"redirect"=>null,"current"=>null];
    if(!$html) return $result;
    if(preg_match('/name="_token"\s+value="([^"]+)"/i',$html,$m)) $result['_token'] = $m[1];
    if(preg_match('/name="visitor_id"\s+value="([^"]*)"/i',$html,$m)) $result['visitor_id'] = $m[1];
    if(preg_match('/name="campaign_id"\s+value="([^"]*)"/i',$html,$m)) $result['campaign_id'] = $m[1];
    if(preg_match('/name="redirect"\s+value="([^"]*)"/i',$html,$m)) $result['redirect'] = $m[1];
    if(preg_match('/name="current"\s+value="([^"]*)"/i',$html,$m)) $result['current'] = $m[1];
    if(!$result['_token'] && preg_match('/<input[^>]*name=[\'"]?_token[\'"]?[^>]*value=[\'"]([^\'"]+)[\'"][^>]*>/i',$html,$m))
        $result['_token']=$m[1];
    return $result;
}

/* === Submit ke Loops === */
function submitToLoops($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
    if (!empty($data['current'])) {
        curl_setopt($ch, CURLOPT_REFERER, $data['current']);
    }
    $response = curl_exec($ch);
    $info     = curl_getinfo($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    $headerSize = $info['header_size'] ?? 0;
    $respHeader = $headerSize ? substr($response, 0, $headerSize) : '';
    $respBody   = $headerSize ? substr($response, $headerSize) : $response;
    writeLog("LOOPS_SUBMIT", [
        "url"=>$info['url'] ?? $url,
        "payload"=>$data,
        "http"=>$info['http_code'] ?? 0,
        "resp_header"=>trim($respHeader),
        "resp_body"=>substr($respBody,0,4000),
        "error"=>$error
    ]);
    return ['http'=>$info['http_code'] ?? 0,'resp_header'=>$respHeader,'resp_body'=>$respBody,'error'=>$error];
}

/* === Meta CAPI helper === */
function sendCapiEvent($event_name, $event_id, $user_data = []) {
    global $CAPI_TOKEN, $META_PIXEL_ID, $TRACKING;
    // Respect tracking config: jika CAPI disabled, jangan kirim
    if(empty($TRACKING['enable_capi']) || !$TRACKING['enable_capi']) {
        writeLog("CAPI_SKIPPED", ["event"=>$event_name,"event_id"=>$event_id]);
        return;
    }
    if(!$CAPI_TOKEN || !$META_PIXEL_ID) return;
    $payload = [
        'data' => [[
            'event_name' => $event_name,
            'event_time' => time(),
            'event_id'   => $event_id,
            'action_source' => 'website',
            'user_data' => $user_data
        ]]
    ];
    $ch = curl_init("https://graph.facebook.com/v17.0/$META_PIXEL_ID/events?access_token=$CAPI_TOKEN");
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>["Content-Type: application/json"],
        CURLOPT_POSTFIELDS=>json_encode($payload)
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    writeLog("CAPI_EVENT_{$event_name}", ["http"=>$http,"resp"=>$resp,"payload"=>$payload]);
}

/* === Main === */
if($_SERVER['REQUEST_METHOD']!=='POST'){
    echo json_encode(['success'=>false,'message'=>'Metode request tidak diizinkan.']);
    exit;
}
$mode = $_POST['mode'] ?? 'form';
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

/*
  Bila client mengirim client_event_id (dari halaman), gunakan sebagai event_id
  untuk CAPI supaya cocok antara client pixel dan server CAPI.
*/
$client_event_id = isset($_POST['client_event_id']) ? trim($_POST['client_event_id']) : null;

if($mode==='form'){
    $name = trim($_POST['name']??'');
    $wa   = formatNumber($_POST['whatsapp']??'');
    if(!$name || !$wa){
        echo json_encode(['success'=>false,'message'=>'Nama & nomor wajib diisi.']); exit;
    }
    if($limit = checkRateLimit($ip,$wa)){
        echo json_encode(['success'=>false,'message'=>$limit]); exit;
    }
    // [$ok,$msg,$showAlt] = validateWithWatzap($wa);
    // if(!$ok){
    //     echo json_encode(['success'=>false,'message'=>$msg,'show_alt'=>$showAlt]); exit;
    // }
    $pageUrl = $LOOPS_CAMPAIGN['page_url'] ?? null;
    $formUrl = $LOOPS_CAMPAIGN['form_url'] ?? null;
    $campaignIdCfg = $LOOPS_CAMPAIGN['campaign_id'] ?? null;
    if(!$pageUrl || !$formUrl){
        writeLog("ERROR","LOOPS config missing page_url or form_url");
        echo json_encode(['success'=>false,'message'=>'Konfigurasi Loops tidak lengkap.']); exit;
    }
    $hidden = getLoopsFormHidden($pageUrl);
    if(empty($hidden['_token'])){
        writeLog("ERROR","Missing _token from loops page",["page"=>$pageUrl,"hidden"=>$hidden]);
        echo json_encode(['success'=>false,'message'=>'❌ Gagal mengambil token form. Coba refresh halaman atau gunakan tombol WhatsApp alternatif.','show_alt'=>true]); exit;
    }
    $loopsData = [
        "_token"=>$hidden['_token'],
        "name"=>$name,
        "phone"=>$wa,
        "campaign_id"=>$hidden['campaign_id'] ?: $campaignIdCfg,
        "visitor_id"=>$hidden['visitor_id'] ?? '',
        "redirect"=>$hidden['redirect'] ?? '',
        "current"=>$hidden['current'] ?? $pageUrl
    ];
    $res = submitToLoops($formUrl,$loopsData);
    $http = intval($res['http']);
    $bodyLower = strtolower($res['resp_body'] ?? '');
    $isExpired = ($http === 419) ||
                 (strpos($bodyLower,'page expired')!==false) ||
                 (strpos($bodyLower,'sorry, your session has expired')!==false) ||
                 (strpos($bodyLower,'expired')!==false && strpos($bodyLower,'loops')!==false);
    if($http >= 200 && $http < 400 && !$isExpired){
        $redirectUrl = null;
        if(!empty($res['resp_header']) && preg_match('/Location:\s*(\S+)/i',$res['resp_header'],$m))
            $redirectUrl = trim($m[1]);
        if(empty($redirectUrl) && !empty($loopsData['redirect'])) $redirectUrl=$loopsData['redirect'];
        if(empty($redirectUrl)) $redirectUrl=$pageUrl;

        // event_id untuk CAPI: jika client sudah kirim client_event_id gunakan itu,
        // kalau tidak gunakan fallback yg sebelumnya (md5)
        $event_id = $client_event_id ?: ('addtocart-'.md5($wa.time()));

        echo json_encode(['success'=>true,'message'=>'Pesanan berhasil dikirim.','redirect_url'=>$redirectUrl]);

        // 🔹 Kirim event AddToCart ke Meta CAPI (jika enabled di config)
        sendCapiEvent(
            ($TRACKING['event_add_to_cart'] ?? 'AddToCart'),
            $event_id,
            ['em'=>hash('sha256', strtolower(trim($name)))]
        );
        exit;
    } else {
        writeLog("LOOPS_SUBMIT_FAILED", ["http"=>$http,"isExpired"=>$isExpired,"resp_body"=>substr($res['resp_body'] ?? '',0,2000)]);
        echo json_encode(['success'=>false,'message'=>'Gagal mengirim pesanan ke Loops (campaign mungkin expired atau token tidak valid).','show_alt'=>true]);
        exit;
    }
}

if($mode==='whatsapp'){
    if($limit = checkRateLimit($ip,'wa')){
        echo json_encode(['success'=>false,'message'=>$limit]); exit;
    }

    // gunakan client_event_id jika ada, sediakan fallback
    $event_id = $client_event_id ?: ('lead-'.md5($ip.time()));

    echo json_encode(['success'=>true,'message'=>'Membuka WhatsApp...','redirect_url'=>$LOOPS_WHATSAPP_URL]);

    // 🔹 Kirim event Lead ke Meta CAPI (jika enabled di config)
    sendCapiEvent(
        ($TRACKING['event_lead'] ?? 'Lead'),
        $event_id,
        ['client_ip_address'=>$ip]
    );
    exit;
}

echo json_encode(['success'=>false,'message'=>'Mode tidak dikenali.']);
