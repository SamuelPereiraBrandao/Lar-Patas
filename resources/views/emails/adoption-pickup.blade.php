<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sua retirada está agendada — Lar &amp; Patas</title>
</head>
<body style="margin:0;padding:0;background:#f2f7f5;font-family:Arial,Helvetica,sans-serif;color:#173b37;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">A retirada de {{ $petName }} está marcada para {{ $pickupDate }} às {{ $pickupTime }}. Confira os detalhes e seu código.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f7f5;">
        <tr><td align="center" style="padding:28px 12px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #deebe5;border-radius:20px;overflow:hidden;">
                <tr><td style="padding:28px 28px;background:#0f766e;color:#ffffff;">
                    <p style="margin:0;font-size:23px;font-weight:bold;">Lar &amp; Patas</p>
                    <p style="margin:8px 0 0;font-size:11px;letter-spacing:2px;">UM NOVO LAR, UMA NOVA HISTÓRIA</p>
                </td></tr>
                <tr><td style="padding:28px 28px 12px;">
                    <p style="margin:0 0 12px;font-size:11px;letter-spacing:1px;font-weight:bold;color:#0f766e;">RETIRADA AGENDADA</p>
                    <h1 style="margin:0 0 14px;font-size:27px;line-height:1.25;color:#173b37;">Está quase na hora de levar {{ $petName }} para casa!</h1>
                    <p style="margin:0;font-size:15px;line-height:1.7;color:#526b65;">{{ $name }}, sua adoção foi aprovada. Agora só falta o encontro e a confirmação da entrega pela nossa equipe.</p>
                </td></tr>
                <tr><td style="padding:12px 28px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f8f6;border:1px solid #e0ede7;border-radius:12px;">
                        <tr><td style="padding:18px 20px 12px;">
                            <p style="margin:0 0 5px;font-size:11px;color:#60736f;letter-spacing:1px;font-weight:bold;">DATA E HORÁRIO</p>
                            <p style="margin:0;font-size:18px;font-weight:bold;color:#173b37;">{{ $pickupDate }} às {{ $pickupTime }}</p>
                            <p style="margin:5px 0 0;font-size:12px;color:#60736f;">Horário da retirada · UTC{{ $timezoneOffset }}</p>
                        </td></tr>
                        <tr><td style="padding:14px 20px 18px;border-top:1px solid #e0ede7;">
                            <p style="margin:0 0 6px;font-size:11px;color:#60736f;letter-spacing:1px;font-weight:bold;">ONDE BUSCAR</p>
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#173b37;white-space:pre-line;overflow-wrap:anywhere;">{{ $location }}</p>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td style="padding:16px 28px;">
                    <p style="margin:0 0 9px;font-size:12px;font-weight:bold;color:#0f766e;">Um recado da equipe</p>
                    <p style="margin:0;font-size:15px;line-height:1.7;color:#526b65;white-space:pre-line;overflow-wrap:anywhere;">{{ $pickupMessage }}</p>
                </td></tr>
                <tr><td style="padding:12px 28px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e8f4ef;border:1px dashed #8dbbad;border-radius:12px;">
                        <tr><td align="center" style="padding:24px 16px;">
                            <p style="margin:0 0 12px;font-size:12px;font-weight:bold;color:#0f766e;">SEU CÓDIGO DE RETIRADA</p>
                            <p style="margin:0;font-size:36px;line-height:1.3;font-family:Consolas,'Courier New',monospace;font-weight:bold;letter-spacing:7px;color:#173b37;">{{ $code }}</p>
                            <p style="margin:12px 0 0;font-size:13px;line-height:1.6;color:#526b65;">Apresente este código à equipe quando chegar.</p>
                        </td></tr>
                    </table>
                </td></tr>
                <tr><td align="center" style="padding:20px 28px 28px;">
                    <table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#0f766e" style="border-radius:10px;text-align:center;">
                        <a href="{{ $url }}" style="display:inline-block;padding:16px 26px;border:1px solid #0f766e;border-radius:10px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">Ver detalhes da retirada</a>
                    </td></tr></table>
                    <p style="margin:20px 0 0;font-size:12px;line-height:1.7;color:#60736f;">Aguardando liberação: {{ $petName }} aparecerá como seu pet após a equipe validar o código e confirmar a entrega.</p>
                </td></tr>
                <tr><td align="center" style="padding:20px;background:#edf5f2;font-size:12px;line-height:1.6;color:#60736f;">Com carinho, equipe Lar &amp; Patas<br>Conectando histórias, formando famílias.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
