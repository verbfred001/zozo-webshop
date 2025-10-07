<?php
// mail_graph.php
// Helper to send email using Microsoft Graph (client credentials)
// Optional config: create `zozo-includes/mail_config.php` that defines
// $MS_TENANT_ID, $MS_CLIENT_ID, $MS_CLIENT_SECRET, $MS_FROM_EMAIL

function fetch_graph_config_from_db($mysqli)
{
    $keys = ['ms_tenant_id', 'ms_client_id', 'ms_client_secret', 'ms_from_email'];
    $out = [];
    foreach ($keys as $k) $out[$k] = null;
    // try reading from instellingen table if exists
    try {
        $r = $mysqli->query("SELECT naam, waarde FROM instellingen WHERE naam IN ('" . implode("','", $keys) . "')");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $out[$row['naam']] = $row['waarde'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return $out;
}

function send_mail_graph($toEmail, $subject, $htmlBody, $replyTo = null, $fromEmail = null)
{
    // load config from file if present
    $cfg = [];
    $confFile = __DIR__ . '/mail_config.php';
    if (file_exists($confFile)) {
        include $confFile; // should set $MS_TENANT_ID, $MS_CLIENT_ID, $MS_CLIENT_SECRET, $MS_FROM_EMAIL
        $cfg['tenant'] = $MS_TENANT_ID ?? null;
        $cfg['client'] = $MS_CLIENT_ID ?? null;
        $cfg['secret'] = $MS_CLIENT_SECRET ?? null;
        $cfg['from'] = $MS_FROM_EMAIL ?? null;
    }

    // fallback to DB (if available)
    if (empty($cfg['tenant']) || empty($cfg['client']) || empty($cfg['secret'])) {
        global $mysqli;
        if (!empty($mysqli)) {
            $dbcfg = fetch_graph_config_from_db($mysqli);
            $cfg['tenant'] = $cfg['tenant'] ?? $dbcfg['ms_tenant_id'];
            $cfg['client'] = $cfg['client'] ?? $dbcfg['ms_client_id'];
            $cfg['secret'] = $cfg['secret'] ?? $dbcfg['ms_client_secret'];
            $cfg['from']   = $cfg['from']   ?? $dbcfg['ms_from_email'];
        }
    }

    if (empty($fromEmail)) $fromEmail = $cfg['from'] ?? null;

    if (empty($cfg['tenant']) || empty($cfg['client']) || empty($cfg['secret']) || empty($fromEmail)) {
        $missing = [];
        if (empty($cfg['tenant'])) $missing[] = 'tenant';
        if (empty($cfg['client'])) $missing[] = 'client';
        if (empty($cfg['secret'])) $missing[] = 'secret';
        if (empty($fromEmail)) $missing[] = 'fromEmail';
        $msg = 'Graph mail config missing: ' . implode(',', $missing);
        error_log($msg);
        // also expose a short diagnostic in session for UI pages (no secrets)
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['last_graph_error'] = $msg;
        return false;
    }

    // require autoload for Graph SDK
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        $msg = 'Composer autoload not found for Graph mailer at ' . $autoload;
        error_log($msg);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['last_graph_error'] = $msg;
        return false;
    }
    require_once $autoload;

    try {
        $attemptMsg = 'Graph mailer: attempting to send mail to ' . $toEmail . ' from ' . $fromEmail . ' subject: ' . substr($subject, 0, 120);
        error_log($attemptMsg);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // clear previous error
        $_SESSION['last_graph_error'] = null;
        // use ClientCredentialContext and GraphServiceClient like in your callback example
        $tokenContext = new \Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext(
            $cfg['tenant'],
            $cfg['client'],
            $cfg['secret'],
            ['https://graph.microsoft.com/.default']
        );
        $graph = new \Microsoft\Graph\GraphServiceClient($tokenContext);

        // build message
        $message = new \Microsoft\Graph\Generated\Models\Message();
        $message->setSubject($subject);

        $body = new \Microsoft\Graph\Generated\Models\ItemBody();
        $body->setContentType(new \Microsoft\Graph\Generated\Models\BodyType('html'));
        $body->setContent($htmlBody);
        $message->setBody($body);

        // to
        $toRecipient = new \Microsoft\Graph\Generated\Models\Recipient();
        $toEmailAddress = new \Microsoft\Graph\Generated\Models\EmailAddress();
        $toEmailAddress->setAddress($toEmail);
        $toRecipient->setEmailAddress($toEmailAddress);
        $message->setToRecipients([$toRecipient]);

        // reply-to
        if (!empty($replyTo)) {
            $replyToRecipient = new \Microsoft\Graph\Generated\Models\Recipient();
            $replyToEmail = new \Microsoft\Graph\Generated\Models\EmailAddress();
            $replyToEmail->setAddress($replyTo);
            $replyToRecipient->setEmailAddress($replyToEmail);
            $message->setReplyTo([$replyToRecipient]);
        }

        // from
        $fromRecipient = new \Microsoft\Graph\Generated\Models\Recipient();
        $fromAddr = new \Microsoft\Graph\Generated\Models\EmailAddress();
        $fromAddr->setAddress($fromEmail);
        $fromRecipient->setEmailAddress($fromAddr);
        $message->setFrom($fromRecipient);

        $sendMailBody = new \Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody();
        $sendMailBody->setMessage($message);
        $sendMailBody->setSaveToSentItems(true);

        // send as the from user
        $graph->users()->byUserId($fromEmail)->sendMail()->post($sendMailBody);
        // success: clear any session-stored error
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['last_graph_error'] = null;
        return true;
    } catch (Throwable $e) {
        // log full exception with trace for debugging (avoid logging secret values)
        $msg = 'Graph send error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        $msg .= ' Trace: ' . substr($e->getTraceAsString(), 0, 2000);
        error_log($msg);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // expose a short message (without trace) to the session for UI debugging
        $_SESSION['last_graph_error'] = 'Graph send error: ' . substr($e->getMessage(), 0, 400);
        return false;
    }
}
