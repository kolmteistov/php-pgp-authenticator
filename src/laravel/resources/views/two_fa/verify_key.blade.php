@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
<div class="col-md-6">
    <div class="card">
        <div class="card-header"><h5>🔑 Verify PGP Key</h5></div>
        <div class="card-body">
            <p class="text-muted">Decrypt the message below using your PGP key, then enter the PIN found inside it.</p>

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <div class="mb-3">
                <label class="form-label fw-bold">Message to decrypt</label>
                <textarea class="form-control font-monospace" rows="10"
                          style="font-size:0.75rem" readonly>{{ $encryptedMessage }}</textarea>
                <button class="btn btn-sm btn-outline-secondary mt-1"
                        onclick="navigator.clipboard.writeText(document.querySelector('textarea').value)">
                    📋 Copy
                </button>
            </div>

            <form method="POST" action="{{ route('two_fa.verify_key_post') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Decrypted PIN</label>
                    <input type="text" name="pin" class="form-control font-monospace"
                           placeholder="Enter the decrypted PIN..."
                           autocomplete="off" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary w-100">✅ Verify</button>
            </form>
        </div>
    </div>
</div>
</div>
@endsection
