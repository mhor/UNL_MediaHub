<?php
if (false == headers_sent()
    && $code = $context->getCode()) {
    header('HTTP/1.1 '.$code.' '.$context->getMessage());
    header('Status: '.$code.' '.$context->getMessage());
}
?>

<script type="text/javascript">
WDN.initializePlugin('notice');
</script>
<div class="wdn_notice alert">
    <div class="message">
        <h1 class="title">Whoops! Sorry, there was an error:</h1>
        <p><?php echo $context->getMessage(); ?></p>
    </div>
</div>