<?php
declare(strict_types=1);
/**
 * Envoi d'emails applicatifs (réinitialisation de mot de passe, alertes…).
 *
 * Deux modes, dans l'ordre de préférence :
 *   1. SMTP direct si le paramètre `mail_smtp_host` est renseigné (Paramètres) —
 *      client SMTP minimal via fsockopen (AUTH LOGIN optionnel, sans TLS).
 *      Convient à un relais LAN / serveur Exchange local.
 *   2. Repli sur la fonction native mail() de PHP.
 *
 * Retour : ['ok' => bool, 'err' => string] (err = '' si ok).
 * Aucune dépendance externe (pas de composer/PHPMailer).
 */
function send_app_mail(string $to, string $subject, string $html): array
{
    $host   = trim(getParam('mail_smtp_host', ''));
    $port   = (int)(getParam('mail_smtp_port', '25') ?: 25);
    $user   = trim(getParam('mail_smtp_user', ''));
    $pass   = getParam('mail_smtp_pass', '');
    $from   = trim(getParam('mail_from', 'pharmacare@' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost')));
    $fromNm = trim(getParam('mail_from_name', 'PharmaCare'));

    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: $fromNm <$from>\r\nMIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\nX-Mailer: PharmaCare";

    // ── Chemin SMTP dédié ────────────────────────────────────────────────
    if ($host !== '') {
        $sock = @fsockopen($host, $port, $errno, $errstr, 6);
        if (!$sock) return ['ok' => false, 'err' => "SMTP : connexion impossible ($errstr)"];

        $read = function () use ($sock): string {
            $data = '';
            while (($line = fgets($sock, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') break; // dernière ligne multi-ligne
            }
            return $data;
        };
        $cmd = function (string $c) use ($sock, $read): array {
            fwrite($sock, $c . "\r\n");
            $resp = $read();
            $code = (int)substr($resp, 0, 3);
            return [$code, $resp];
        };

        try {
            $greet = $read();
            if ((int)substr($greet, 0, 3) !== 220) throw new RuntimeException('SMTP banner : ' . trim($greet));
            [$c, $r] = $cmd('EHLO pharmacare');            if ($c !== 250) throw new RuntimeException('EHLO : ' . trim($r));
            if ($user !== '') {
                [$c, $r] = $cmd('AUTH LOGIN');              if ($c !== 334) throw new RuntimeException('AUTH : ' . trim($r));
                [$c, $r] = $cmd(base64_encode($user));      if ($c !== 334) throw new RuntimeException('AUTH user : ' . trim($r));
                [$c, $r] = $cmd(base64_encode($pass));      if ($c !== 235) throw new RuntimeException('AUTH pass refusé');
            }
            [$c, $r] = $cmd("MAIL FROM:<$from>");           if ($c !== 250) throw new RuntimeException('MAIL FROM : ' . trim($r));
            [$c, $r] = $cmd("RCPT TO:<$to>");               if ($c !== 250) throw new RuntimeException('RCPT : ' . trim($r));
            [$c, $r] = $cmd('DATA');                        if ($c !== 354) throw new RuntimeException('DATA : ' . trim($r));
            // Dot-stuffing RFC 5321 + terminaison
            $body = preg_replace('/^\./m', '..', $html);
            fwrite($sock, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");
            $resp = $read();
            if ((int)substr($resp, 0, 3) !== 250) throw new RuntimeException('corps refusé : ' . trim($resp));
            fwrite($sock, "QUIT\r\n");
            fclose($sock);
            return ['ok' => true, 'err' => ''];
        } catch (Throwable $e) {
            @fclose($sock);
            return ['ok' => false, 'err' => $e->getMessage()];
        }
    }

    // ── Repli : mail() natif ─────────────────────────────────────────────
    $ok = @mail($to, $subject, $html, $headers);
    return ['ok' => (bool)$ok, 'err' => $ok ? '' : 'mail() a retourné false (SMTP du serveur non configuré)'];
}