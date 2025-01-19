<?php
error_reporting(E_ERROR | E_PARSE); // ซ่อน Notice และ Warning

$output = [];
$notificationMessage = '';
$notificationType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ข้อมูลเซิร์ฟเวอร์ IMAP
    $imapHost = '{mailstock2.com:143/imap}INBOX'; // ใช้ SSL สำหรับการเชื่อมต่อที่ปลอดภัย

    // ลองเชื่อมต่อกับ IMAP
    $inbox = @imap_open($imapHost, $username, $password);

    if ($inbox) {
        // ค้นหาอีเมลที่มีหัวข้อ "Your Rockstar Games verification code"
        $emails = imap_search($inbox, 'SUBJECT "Your Rockstar Games verification code"');

        if ($emails) {
            rsort($emails); // เรียงอีเมลจากใหม่ไปเก่า
            foreach ($emails as $email_number) {
                $overview = imap_fetch_overview($inbox, $email_number, 0);
                $message = imap_fetchbody($inbox, $email_number, 1);

                // ตรวจสอบการเข้ารหัส Base64 และถอดรหัสหากจำเป็น
                $decodedMessage = base64_decode($message, true) ?: $message;

                // ดึงเฉพาะตัวเลข 6 หลัก
                if (preg_match('/\b\d{6}\b/', $decodedMessage, $matches)) {
                    $verificationCode = $matches[0];
                } else {
                    $verificationCode = 'No code found';
                }

                // เก็บข้อมูลในอาเรย์
                $output[] = [
                    'subject' => $overview[0]->subject ?? 'No Subject',
                    'from' => $overview[0]->from ?? 'Unknown Sender',
                    'date' => $overview[0]->date ?? 'Unknown Date',
                    'verification_code' => $verificationCode
                ];
            }
            $notificationMessage = "Emails found and verification code extracted.";
            $notificationType = "success"; // แจ้งเตือนสำเร็จ
        } else {
            $notificationMessage = "No emails found with the specified subject.";
            $notificationType = "error"; // แจ้งเตือนข้อผิดพลาด
        }

        imap_close($inbox);
    } else {
        // แสดงข้อความข้อผิดพลาดจาก imap_last_error()
        $imapError = imap_last_error();
        $notificationMessage = "Error: Unable to connect to IMAP server. Details: $imapError";
        $notificationType = "error"; // แจ้งเตือนข้อผิดพลาด
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Email Viewer</title>
</head>
<body>
    <div class="container">
        <h1>Email Viewer</h1>
        <form method="post">
            <div class="form-group">
                <label for="username">Email Address:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Fetch Emails</button>
        </form>

        <hr>

        <?php if (!empty($output)): ?>
            <?php if (is_array($output[0])): ?>
                <?php foreach ($output as $email): ?>
                    <div class="email-item">
                        <div class="email-header"><?= htmlspecialchars($email['subject']) ?></div>
                        <div class="email-date"><?= htmlspecialchars($email['date']) ?></div>
                        <div class="email-body">
                            <p><strong>Verification Code:</strong> <?= htmlspecialchars($email['verification_code']) ?></p>
                            <button class="copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($email['verification_code']) ?>')">Copy Code</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="error"><?= htmlspecialchars($output[0]) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    // ฟังก์ชันในการแสดง Notification
    function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.classList.add('notification');

            // กำหนดประเภทของ Notification
            if (type === 'error') {
                notification.classList.add('notification-error');
            } else if (type === 'success') {
                notification.classList.add('notification-success');
            }

            notification.textContent = message; // ข้อความที่จะแสดง
            document.body.appendChild(notification); // เพิ่ม notification ไปที่ body

            // ลบ notification หลังจาก 3 วินาที
            setTimeout(() => {
                notification.remove();
            }, 3000); // 3000ms = 3 วินาที
        }

        // แสดงการแจ้งเตือนจาก PHP
        <?php if (!empty($notificationMessage)): ?>
            showNotification("<?= addslashes($notificationMessage) ?>", "<?= $notificationType ?>");
        <?php endif; ?>

    // การใช้งาน
    // แสดงข้อความเมื่อคัดลอกโค้ดสำเร็จ
    function copyToClipboard(code) {
        const textarea = document.createElement('textarea');
        textarea.value = code;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        // เรียกใช้ Notification เมื่อคัดลอกสำเร็จ
        showNotification('Verification code copied to clipboard!', 'success');
    }

    // ตัวอย่างการแสดงข้อผิดพลาด
    function handleError(errorMessage) {
        showNotification(errorMessage, 'error');
    }

    </script>

</body>
</html>
