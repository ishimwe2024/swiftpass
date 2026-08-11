<?php
session_start();

// ===== Authentication =====
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'staff'])) {
    header("Location: login.php");
    exit;
}

// ===== Generate CSRF token =====
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];
$user_name = $_SESSION['user_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SwiftPass – Ticket Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: linear-gradient(145deg, #0b1a33 0%, #1a2f4f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .scanner-wrapper {
            max-width: 720px;
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            border-radius: 32px;
            padding: 2rem 2rem 2.2rem;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .scanner-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .scanner-header-left {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .scanner-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.4rem;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
        }

        .scanner-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0b1a33;
            letter-spacing: -0.02em;
        }

        .scanner-title span {
            color: #2563eb;
        }

        .staff-badge {
            background: #e8edf5;
            padding: 0.45rem 1rem;
            border-radius: 100px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #1a2f4f;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .staff-badge i {
            color: #2563eb;
        }

        .scanner-sub {
            text-align: center;
            color: #5f6c80;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        /* Scanner Frame */
        .scanner-frame {
            position: relative;
            width: 100%;
            max-width: 440px;
            margin: 0 auto 1.8rem;
            border-radius: 24px;
            overflow: hidden;
            background: #0f1a2b;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #reader {
            width: 100% !important;
            height: 100% !important;
        }

        #reader video {
            border-radius: 24px !important;
            object-fit: cover;
        }

        /* Overlay corners */
        .scanner-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 2;
        }

        .scanner-overlay::before,
        .scanner-overlay::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid #fff;
            border-radius: 4px;
        }

        .scanner-overlay::before {
            top: 16px;
            left: 16px;
            border-right: none;
            border-bottom: none;
        }

        .scanner-overlay::after {
            bottom: 16px;
            right: 16px;
            border-left: none;
            border-top: none;
        }

        .scanner-corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid #fff;
            border-radius: 4px;
            pointer-events: none;
        }

        .scanner-corner.tl {
            top: 16px;
            left: 16px;
            border-right: none;
            border-bottom: none;
        }
        .scanner-corner.tr {
            top: 16px;
            right: 16px;
            border-left: none;
            border-bottom: none;
        }
        .scanner-corner.bl {
            bottom: 16px;
            left: 16px;
            border-right: none;
            border-top: none;
        }
        .scanner-corner.br {
            bottom: 16px;
            right: 16px;
            border-left: none;
            border-top: none;
        }

        /* Pulsing line */
        .scan-line {
            position: absolute;
            left: 10%;
            right: 10%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #60a5fa, transparent);
            top: 20%;
            z-index: 3;
            animation: scanMove 2.4s ease-in-out infinite;
            opacity: 0.7;
            pointer-events: none;
            border-radius: 2px;
            box-shadow: 0 0 20px rgba(96, 165, 250, 0.5);
        }

        @keyframes scanMove {
            0% { top: 20%; opacity: 0.4; }
            50% { top: 80%; opacity: 1; }
            100% { top: 20%; opacity: 0.4; }
        }

        /* Result box */
        .result-box {
            margin-top: 1.2rem;
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            text-align: center;
            font-weight: 600;
            display: none;
            animation: slideUp 0.4s ease;
            border: 1px solid transparent;
        }

        .result-box.show {
            display: block;
        }

        .result-box.success {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: #065f46;
        }

        .result-box.error {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .result-box.info {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1e40af;
        }

        .result-box .icon {
            font-size: 2.8rem;
            margin-bottom: 0.5rem;
        }

        .result-box .message {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .result-box .detail {
            font-weight: 400;
            font-size: 0.9rem;
            margin-top: 0.3rem;
            opacity: 0.8;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Manual entry */
        .manual-section {
            margin-top: 1.8rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .manual-section label {
            font-weight: 600;
            color: #1a2f4f;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .manual-section .input-group {
            display: flex;
            gap: 0.6rem;
        }

        .manual-section input {
            flex: 1;
            padding: 0.7rem 1rem;
            border: 2px solid #d1d9e6;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .manual-section input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .btn-verify {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            padding: 0.7rem 1.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: transform 0.15s, box-shadow 0.2s;
            white-space: nowrap;
        }

        .btn-verify:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
        }

        .btn-verify:active {
            transform: scale(0.98);
        }

        .btn-restart {
            background: #e8edf5;
            color: #1a2f4f;
            border: none;
            padding: 0.7rem 1.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: background 0.2s;
        }

        .btn-restart:hover {
            background: #d1d9e6;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            justify-content: center;
            margin-top: 0.8rem;
        }

        /* Spinner */
        .spinner-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(15, 26, 43, 0.7);
            backdrop-filter: blur(4px);
            border-radius: 24px;
            z-index: 10;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            flex-direction: column;
            gap: 0.8rem;
        }

        .spinner-overlay.show {
            display: flex;
        }

        .spinner-overlay .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top: 4px solid #60a5fa;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .scanner-wrapper {
                padding: 1.2rem;
            }
            .scanner-title {
                font-size: 1.2rem;
            }
            .scanner-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .scanner-header-left {
                justify-content: center;
            }
            .staff-badge {
                align-self: center;
            }
            .manual-section .input-group {
                flex-direction: column;
            }
            .btn-verify {
                width: 100%;
                justify-content: center;
            }
            .action-row {
                flex-direction: column;
            }
            .btn-restart {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="scanner-wrapper">
    <!-- Header -->
    <div class="scanner-header">
        <div class="scanner-header-left">
            <div class="scanner-icon">
                <i class="fas fa-qrcode"></i>
            </div>
            <div class="scanner-title">
                Scan <span>Ticket</span>
            </div>
        </div>
        <div class="staff-badge">
            <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($user_name); ?>
        </div>
    </div>
    <p class="scanner-sub">
        <i class="fas fa-camera me-1"></i> Position the ticket’s QR code inside the frame
    </p>

    <!-- Scanner Frame -->
    <div class="scanner-frame" id="scannerFrame">
        <div id="reader"></div>
        <!-- Overlay corners -->
        <div class="scanner-corner tl"></div>
        <div class="scanner-corner tr"></div>
        <div class="scanner-corner bl"></div>
        <div class="scanner-corner br"></div>
        <!-- Animated scan line -->
        <div class="scan-line"></div>
        <!-- Spinner overlay -->
        <div class="spinner-overlay" id="spinnerOverlay">
            <div class="spinner"></div>
            <span>Verifying...</span>
        </div>
    </div>

    <!-- Result Box -->
    <div id="resultBox" class="result-box">
        <div class="icon" id="resultIcon"></div>
        <div class="message" id="resultMessage"></div>
        <div class="detail" id="resultDetail"></div>
    </div>

    <!-- Manual Entry -->
    <div class="manual-section">
        <label><i class="fas fa-keyboard me-1"></i> Manual Entry (paste QR content)</label>
        <div class="input-group">
            <input type="text" id="manualTicket" class="form-control" placeholder="e.g. SWIFTPASS|123|456|abc...">
            <button onclick="verifyManual()" class="btn-verify">
                <i class="fas fa-search"></i> Verify
            </button>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-row">
        <button onclick="restartScanner()" class="btn-restart">
            <i class="fas fa-sync-alt"></i> Scan Again
        </button>
    </div>

    <!-- Hidden CSRF token -->
    <input type="hidden" id="csrfToken" value="<?php echo $csrf_token; ?>">
</div>

<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    let html5QrCode;
    const csrfToken = document.getElementById('csrfToken').value;
    const resultBox = document.getElementById('resultBox');
    const spinner = document.getElementById('spinnerOverlay');

    // ====== Start scanner ======
    function startScanner() {
        const readerElement = document.getElementById('reader');

        html5QrCode = new Html5Qrcode(readerElement.id);

        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };

        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).catch(err => {
            console.error("Camera error:", err);
            showResult('error', 'Camera access denied or not available. Please use manual entry.');
        });
    }

    // ====== On scan ======
    function onScanSuccess(decodedText) {
        // Stop scanner
        if (html5QrCode) {
            html5QrCode.stop().catch(err => console.warn("Stop error:", err));
        }

        // Parse "SWIFTPASS|ticket_id|booking_id|hash"
        const parts = decodedText.split('|');
        if (parts.length !== 4 || parts[0] !== 'SWIFTPASS') {
            showResult('error', 'Invalid QR code format. Please scan a valid ticket.');
            return;
        }

        const ticket_id = parseInt(parts[1]);
        const booking_id = parseInt(parts[2]);
        const hash = parts[3];

        if (isNaN(ticket_id) || isNaN(booking_id) || !hash) {
            showResult('error', 'Invalid ticket data.');
            return;
        }

        // Send verification
        verifyTicket(ticket_id, booking_id, hash);
    }

    function onScanError(err) {
        // ignore
    }

    // ====== Verify ticket via API ======
    async function verifyTicket(ticket_id, booking_id, hash) {
        // Show spinner
        spinner.classList.add('show');

        try {
            const response = await fetch('verify_ticket_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id, booking_id, hash, csrf_token: csrfToken })
            });

            const data = await response.json();

            spinner.classList.remove('show');

            if (data.success) {
                showResult('success', '✅ ' + data.message, data.verified_at ? 'Verified at: ' + data.verified_at : '');
            } else {
                if (data.message && data.message.includes('already')) {
                    showResult('info', 'ℹ️ ' + data.message);
                } else {
                    showResult('error', '❌ ' + (data.message || 'Verification failed.'));
                }
            }
        } catch (error) {
            console.error('Verification error:', error);
            spinner.classList.remove('show');
            showResult('error', 'Network error. Please try again.');
        }
    }

    // ====== Restart scanner ======
    function restartScanner() {
        // Hide result
        resultBox.classList.remove('show');
        resultBox.className = 'result-box';
        spinner.classList.remove('show');

        if (html5QrCode) {
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess,
                onScanError
            ).catch(err => console.warn("Restart error:", err));
        } else {
            startScanner();
        }
    }

    // ====== Manual entry ======
    function verifyManual() {
        const input = document.getElementById('manualTicket').value.trim();
        if (!input) {
            showResult('error', 'Please paste the QR content or ticket ID.');
            return;
        }

        // Try to parse as custom format
        const parts = input.split('|');
        if (parts.length === 4 && parts[0] === 'SWIFTPASS') {
            const ticket_id = parseInt(parts[1]);
            const booking_id = parseInt(parts[2]);
            const hash = parts[3];
            if (!isNaN(ticket_id) && !isNaN(booking_id) && hash) {
                verifyTicket(ticket_id, booking_id, hash);
                return;
            }
        }

        showResult('error', 'Invalid format. Please paste the full QR content (e.g., SWIFTPASS|123|456|hash).');
    }

    // ====== UI helper ======
    function showResult(type, message, detail = '') {
        resultBox.className = 'result-box show ' + type;
        const iconMap = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };
        document.getElementById('resultIcon').innerHTML = iconMap[type] || iconMap.info;
        document.getElementById('resultMessage').textContent = message;
        document.getElementById('resultDetail').textContent = detail || '';
        // Auto-restart after 6 seconds for success/error/info
        if (type !== 'error' || !detail.includes('Network')) {
            setTimeout(() => {
                restartScanner();
            }, 6000);
        }
    }

    // ====== Start ======
    document.addEventListener('DOMContentLoaded', function() {
        startScanner();
    });

    // Allow Enter key on manual input
    document.getElementById('manualTicket').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            verifyManual();
        }
    });
</script>
</body>
</html>