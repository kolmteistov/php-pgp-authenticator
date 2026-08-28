<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>Two-Factor Authentication — {{ config('pgp2fa.site_name', config('app.name')) }}</title>
    <link rel="stylesheet" href="{{ asset('css/tabler.min.css') }}">
</head>
<body class="d-flex flex-column">
<div class="page page-center">
    <div class="container container-tight py-4">

        {{-- Brand --}}
        <div class="text-center mb-4">
            <span class="avatar avatar-lg mb-3" style="font-size:1.5rem">🔑</span>
            <h2 class="mb-0">Two-Factor Authentication</h2>
            <p class="text-muted mt-1">Decrypt the message below using your PGP key</p>
        </div>

        <div class="card card-md">
            <div class="card-body">

                @if($errors->any())
                    <div class="alert alert-danger mb-3">
                        <div class="d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon me-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {{ $errors->first() }}
                        </div>
                    </div>
                @endif

                {{-- Encrypted message --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Encrypted Message</label>
                        <button type="button" class="btn btn-sm btn-ghost-secondary"
                                onclick="navigator.clipboard.writeText(document.getElementById('pgp-msg').value).then(() => this.textContent = '✓ Copied')">
                            Salin
                        </button>
                    </div>
                    <textarea id="pgp-msg" class="form-control font-monospace"
                              rows="10" style="font-size:0.72rem;resize:none"
                              readonly>{{ $encryptedMessage }}</textarea>
                    <div class="form-hint mt-1">
                        Use GPG/Kleopatra to decrypt the message, then enter the PIN you find inside.
                    </div>
                </div>

                <form method="POST" action="{{ route('two_fa.verify') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Verification PIN</label>
                        <input type="text" name="pin" class="form-control font-monospace"
                               placeholder="Enter the decrypted PIN..."
                               autocomplete="off" autofocus required>
                    </div>
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><polyline points="20 6 9 17 4 12"/></svg>
                            Verify
                        </button>
                    </div>
                </form>

            </div>

            <div class="card-footer text-center">
                <a href="{{ route('login') }}" class="text-muted small">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Back to Login
                </a>
            </div>
        </div>

    </div>
</div>
<script src="{{ asset('js/tabler.min.js') }}"></script>
</body>
</html>