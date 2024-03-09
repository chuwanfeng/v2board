<?php
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$isWeixin = stripos($userAgent, 'micromessenger') !== false; // 微信内
$isAndroid = stripos($userAgent, 'android') !== false; // android终端
$isIOS = preg_match('/(iPhone|iPad|iPod|iOS|Mac)/i', $userAgent); // iOS终端

// 微信内
if ($isWeixin) {
    echo '请在浏览器上打开';
} else {
    // Android端
    if ($isAndroid) {
        // 安卓app的scheme协议
        header('Location: heima://payment/');
        //echo '<script>setTimeout(function () { window.location.href = "https://a.app.qq.com/o/simple.jsp?pkgname=com.lucky.luckyclient"; }, 1000);</script>';
    }
    // iOS端
    if ($isIOS) {
        // 根据时间来判断移动端是否装有app，如果有跳转到和移动端约定好的schema,如果没有装有app则跳转到App Store
        $loadDateTime = time();
        // iOS的scheme协议
        header('Location: heima://payment/');
        //echo '<script>setTimeout(function () { var $timeOutDateTime = time(); if ($timeOutDateTime - $loadDateTime < 5000) { window.location.href = "http://itunes.apple.com/app/id387682726"; } }, 3000);</script>';
    }
}
