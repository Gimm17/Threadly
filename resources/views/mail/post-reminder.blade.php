<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Reminder</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f7; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px 32px; color: #ffffff; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header p { margin: 4px 0 0; opacity: 0.9; font-size: 14px; }
        .body { padding: 32px; }
        .info-card { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #6366f1; }
        .info-card .label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-card .value { font-size: 15px; color: #1f2937; font-weight: 500; }
        .hook-preview { background: #fefce8; border: 1px solid #fde68a; border-radius: 8px; padding: 16px; margin-bottom: 20px; font-style: italic; color: #92400e; }
        .btn { display: inline-block; background: #6366f1; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .btn:hover { background: #4f46e5; }
        .footer { padding: 20px 32px; background: #f9fafb; text-align: center; font-size: 12px; color: #9ca3af; }
        .grid { display: flex; gap: 12px; margin-bottom: 20px; }
        .grid .info-card { flex: 1; margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Pengingat Posting</h1>
            <p>Post kamu akan segera tayang!</p>
        </div>

        <div class="body">
            <p style="color: #374151; font-size: 15px; margin-top: 0;">
                Hai! Ini pengingat bahwa kamu memiliki post yang dijadwalkan untuk segera dipublikasikan.
            </p>

            @if($hookPreview)
            <div class="hook-preview">
                <strong>Hook:</strong> {{ $hookPreview }}
            </div>
            @endif

            <div class="grid">
                <div class="info-card">
                    <div class="label">Jadwal Posting</div>
                    <div class="value">{{ $scheduledTime }}</div>
                </div>
                <div class="info-card">
                    <div class="label">Content Pillar</div>
                    <div class="value">{{ $pillarName }}</div>
                </div>
            </div>

            <p style="margin-bottom: 24px; color: #6b7280; font-size: 14px;">
                Pastikan konten sudah siap dan sesuai sebelum waktu posting. Klik tombol di bawah untuk mereview post kamu.
            </p>

            <div style="text-align: center;">
                <a href="{{ $editUrl }}" class="btn">Review & Edit Post</a>
            </div>
        </div>

        <div class="footer">
            <p>Email ini dikirim otomatis oleh <strong>Threadly</strong> — Content Manager untuk Threads.</p>
            <p>© {{ date('Y') }} Gimora Digital</p>
        </div>
    </div>
</body>
</html>
