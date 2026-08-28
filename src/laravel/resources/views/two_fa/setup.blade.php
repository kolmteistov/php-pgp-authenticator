@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
<div class="col-md-7">

    @if($errors->has('error'))
        <div class="alert alert-danger">{{ $errors->first('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h5>🔐 Two-Factor Authentication (PGP)</h5></div>
        <div class="card-body">

            {{-- Status --}}
            <div class="mb-4">
                <table class="table table-sm">
                    <tr>
                        <td>PGP Key</td>
                        <td>
                            @if($user->pgp_public_key)
                                <span class="badge bg-success">✅ Saved</span>
                            @else
                                <span class="badge bg-secondary">Not set</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Key Verification</td>
                        <td>
                            @if($user->pgp_verified)
                                <span class="badge bg-success">✅ Verified</span>
                            @else
                                <span class="badge bg-warning text-dark">Not verified</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Status 2FA</td>
                        <td>
                            @if($user->two_fa_enabled)
                                <span class="badge bg-success">✅ Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            {{-- Toggle 2FA --}}
            @if($user->pgp_verified)
            <form method="POST" action="{{ route('two_fa.toggle') }}" class="mb-3">
                @csrf
                <button type="submit" class="btn {{ $user->two_fa_enabled ? 'btn-warning' : 'btn-success' }} w-100">
                    {{ $user->two_fa_enabled ? '⏸ Disable 2FA' : '▶ Enable 2FA' }}
                </button>
            </form>
            @endif

            {{-- Hapus Key --}}
            @if($user->pgp_public_key)
            <form method="POST" action="{{ route('two_fa.remove_key') }}"
                  onsubmit="return confirm('Remove PGP key and disable 2FA?')">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 mb-3">🗑 Remove PGP Key</button>
            </form>
            @endif

        </div>
    </div>

    {{-- Form tambah/ganti PGP key --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">{{ $user->pgp_public_key ? '🔄 Replace PGP Public Key' : '➕ Add PGP Public Key' }}</h6>
        </div>
        <div class="card-body">
            @if($errors->has('pgp_public_key'))
                <div class="alert alert-danger">{{ $errors->first('pgp_public_key') }}</div>
            @endif

            <p class="text-muted small">
                Paste your PGP public key below. RSA, DSA, and ECDSA (ed25519) formats are supported.
                The key must include an encryption subkey.
            </p>

            <form method="POST" action="{{ route('two_fa.save_key') }}">
                @csrf
                <div class="mb-3">
                    <textarea name="pgp_public_key" class="form-control font-monospace"
                              rows="8" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----
...
-----END PGP PUBLIC KEY BLOCK-----" required>{{ old('pgp_public_key') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">💾 Save &amp; Verify Key</button>
            </form>
        </div>
    </div>

</div>
</div>
@endsection
