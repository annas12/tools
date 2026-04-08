<?php

// ================== CONFIG.PHP ==================



// Zona waktu

date_default_timezone_set("Asia/Jakarta");



// 🔹 API Watzap

$WATZAP_API_KEY = "SIFXRWWKTKXIJYB6";

$WATZAP_NUMBER_KEY = "5bmxEo52RkRMfJ7D";



// 🔹 Meta Pixel / CAPI

$META_PIXEL_ID = "374791485119548";

$CAPI_TOKEN = "EAAMALbCgpNgBQe4kqc14RlIjUcYNsAPTiYIqzQzhTmmPaLmXmQFSdY0nKBKiiEV0cYxImMfSXc094iqi8ZAZCmvvydXKMYW0fdyZB3rui8q3J4Kf96uiZBlNCVRInQAxtHbtAZCWdMCeZBAYwEyU5UIZCxJH24CKleDuxSIlvsvipquKJn26DEau7z17uyUzAZDZD";



// 🔹 Data Campaign Loops (form)

$LOOPS_CAMPAIGN = [

    "page_url" => "https://mauorder.online/14hari-sembuh-form",

    "slug" => "14hari-sembuh-form",

    "form_url" => "https://app.loops.id/save-order/14hari-sembuh-form",

    "campaign_id" => "290969"

];



// 🔹 Campaign WhatsApp

$LOOPS_WHATSAPP_URL = "https://mauorder.online/14hari-sembuh-wa";



// 🔹 Nomor CS backup

$BACKUP_WA = "https://wa.me/6281234567890";



// 🔹 Rate limit

$RATE_LIMIT_FILE = __DIR__ . "/rate_limit.json";

$RATE_LIMIT_WINDOW = 86400; // 1 hari

$RATE_LIMIT_MAX_IP = 4;

$RATE_LIMIT_MAX_NUMBER = 3;

$RATE_LIMIT_MAX_IP_WA = 4;



// 🔹 Log file

$DEBUG_LOG_FILE = __DIR__ . "/debug.log";



/* ================= Tracking configuration =================

   Anda bisa ubah konfigurasi di sini untuk fleksibilitas:

   - enable_client_pixel : apakah ingin memanggil fbq() di client

   - enable_capi         : apakah server akan mengirim event ke Meta CAPI

   - event names         : nama event untuk AddToCart & Lead

   - event_prefix        : prefix bila ingin menandai event_id khusus

*/

$TRACKING = [

    'enable_client_pixel' => true,   // pemanggilan fbq di browser

    'enable_capi' => true,   // server -> Meta CAPI

    'event_add_to_cart' => 'AddToCart',

    'event_lead' => 'Lead',

    'event_prefix' => 'e',    // prefix untuk event_id jika ingin

];

