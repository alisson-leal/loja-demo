<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin: 0; padding: 0; background: #eef3fb; font-family: Arial, Helvetica, sans-serif; color: #2b2340;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #eef3fb;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background: #0b3fbf; background: linear-gradient(135deg, #0b3fbf, #1677ff 55%, #00c8ff); padding: 24px; text-align: center;">
                            <div style="font-size: 26px; font-weight: bold; color: #ffffff;">{{ $store['name'] }}</div>
                            <div style="font-size: 13px; color: #ffffff; opacity: .9;">{{ $store['tagline'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px 24px; font-size: 16px; line-height: 1.6;">
                            {!! nl2br(e($bodyText)) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 24px; background: #f5f8fd; font-size: 13px; color: #6b6480; text-align: center; line-height: 1.6;">
                            Instagram {{ $store['instagram'] }} &middot; WhatsApp {{ $store['whatsapp'] }}<br>
                            Você recebeu este e-mail porque autorizou o contato da {{ $store['name'] }}.<br>
                            <a href="{{ $unsubscribeUrl }}" style="color: #6b6480;">Não quero mais receber e-mails</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
