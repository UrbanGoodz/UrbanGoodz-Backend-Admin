@extends('business.layouts.app')

@section('title', translate('Scan Packages') . ' - ' . ($manifest->manifest_name ?? ''))

@push('script')
<script>
    let activeManifestId = {{ $manifest->id }};
    let sessionId = '{{ $manifest->manifest_session_id }}';

    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('barcode-input');
        const feedback = document.getElementById('scan-feedback');
        const list = document.getElementById('scanned-list');

        if (input) {
            input.focus();
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitBarcode(input.value.trim());
                }
            });
        }

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            const camSection = document.getElementById('camera-section');
            if (camSection) camSection.classList.remove('d-none');
        }
    });

    function submitBarcode(barcode) {
        const input = document.getElementById('barcode-input');
        const feedback = document.getElementById('scan-feedback');
        const list = document.getElementById('scanned-list');
        const countBadge = document.getElementById('scan-count');

        if (!barcode) return;
        input.disabled = true;
        feedback.innerHTML = '<span class="text-info">' + '{{ translate('Scanning...') }}' + '</span>';

        fetch('{{ route("business.packages.scan.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                barcode,
                manifest_session_id: sessionId,
                manifest_id: activeManifestId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (data.duplicate) {
                    feedback.innerHTML = '<span class="text-warning">' + data.message + '</span>';
                } else {
                    feedback.innerHTML = '<span class="text-success">' + data.message + '</span>';
                }
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="text-center">${list.querySelectorAll('tr').length + 1}</td>
                    <td><code>${data.package.barcode}</code></td>
                    <td><code>${data.package.tracking_id}</code></td>
                    <td><span class="badge badge-soft-${data.duplicate ? 'secondary' : 'warning'}">${data.duplicate ? '{{ translate('rescanned') }}' : 'pending_review'}</span></td>
                    <td><small>{{ $user->name }}</small></td>
                    <td>${new Date().toLocaleTimeString()}</td>
                `;
                list.prepend(row);
                if (countBadge) {
                    countBadge.textContent = parseInt(countBadge.textContent) + (data.duplicate ? 0 : 1);
                }
            } else {
                feedback.innerHTML = '<span class="text-danger">' + data.message + '</span>';
            }
            input.value = '';
            input.disabled = false;
            input.focus();
        })
        .catch(err => {
            feedback.innerHTML = '<span class="text-danger">' + '{{ translate('Scan failed') }}' + '</span>';
            input.disabled = false;
            input.focus();
        });
    }

    let cameraStream = null;

    function toggleCamera() {
        const video = document.getElementById('camera-preview');
        const btn = document.getElementById('camera-toggle-btn');

        if (cameraStream) {
            cameraStream.getTracks().forEach(t => t.stop());
            cameraStream = null;
            video.classList.add('d-none');
            btn.innerHTML = '<i class="tio-camera"></i> {{ translate('Start Camera Scan') }}';
            btn.className = 'btn btn-lg btn-outline--primary w-100';
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
            .then(stream => {
                cameraStream = stream;
                video.srcObject = stream;
                video.classList.remove('d-none');
                btn.innerHTML = '<i class="tio-stop"></i> {{ translate('Stop Camera') }}';
                btn.className = 'btn btn-lg btn-outline-danger w-100';
                scanBarcode();
            })
            .catch(() => {
                alert('{{ translate('Camera not available. Use manual entry.') }}');
            });
    }

    function scanBarcode() {
        if (!cameraStream) return;
        if ('BarcodeDetector' in window) {
            const detector = new BarcodeDetector({ formats: ['qr_code', 'ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'] });
            const video = document.getElementById('camera-preview');
            const detect = () => {
                if (!cameraStream) return;
                detector.detect(video).then(codes => {
                    if (codes.length > 0) {
                        submitBarcode(codes[0].rawValue);
                    }
                }).catch(() => {});
                requestAnimationFrame(detect);
            };
            detect();
        }
    }
</script>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="page-header-title mb-0">{{ translate('Scan Packages') }}</h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Manifest') }}: <strong>{{ $manifest->manifest_name ?? translate('Unnamed Manifest') }}</strong>
                &middot; {{ translate('Employee') }}: <strong>{{ $user->name }}</strong>
            </p>
        </div>
        <div class="d-flex gap-1">
            <a href="{{ route('business.manifests.show', $manifest->id) }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Manifest') }}
            </a>
            <a href="{{ route('business.manifests.index') }}" class="btn btn-outline--primary">
                <i class="tio-clipboard"></i> {{ translate('All Manifests') }}
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="fw-bold small">{{ translate('Manifest') }}:</span>
                <span class="badge badge-soft-primary" id="scan-count">{{ $manifest->total_packages }}</span>
                <span class="small text-muted" style="color: #6c757d !important;">
                    {{ $manifest->service_date?->format('M d, Y') ?? '-' }}
                    @if($manifest->service_type)
                    &middot; {{ ucwords(str_replace('_', ' ', $manifest->service_type)) }}
                    @endif
                </span>
                <span class="text-muted small ms-auto" style="color: #6c757d !important;">
                    <i class="tio-barcode"></i> {{ $manifest->manifest_session_id ? substr($manifest->manifest_session_id, 0, 8) . '...' : '' }}
                </span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-md-7">
            <div class="card mb-3">
                <div class="card-body text-center py-4">
                    <div style="font-size: 3rem; color: var(--ug-primary); margin-bottom: 0.75rem;">
                        <i class="tio-barcode"></i>
                    </div>
                    <h5>{{ translate('Scan a package') }}</h5>
                    <p class="text-muted small mb-3" style="color: #6c757d !important;">
                        {{ translate('Point your scanner gun, use the camera, or type the barcode') }}
                    </p>
                    <div class="row justify-content-center">
                        <div class="col-12 col-sm-8">
                            <input type="text"
                                   id="barcode-input"
                                   class="form-control form-control-lg text-center"
                                   placeholder="{{ translate('Scan or type barcode...') }}"
                                   autofocus
                                   autocomplete="off">
                        </div>
                    </div>
                    <div id="scan-feedback" class="mt-2" style="min-height: 24px; font-weight: 600;"></div>
                </div>
            </div>

            <div id="camera-section" class="card mb-3 d-none">
                <div class="card-body text-center">
                    <button type="button" id="camera-toggle-btn" class="btn btn-lg btn-outline--primary w-100 mb-3" onclick="toggleCamera()">
                        <i class="tio-camera"></i> {{ translate('Start Camera Scan') }}
                    </button>
                    <video id="camera-preview" class="d-none w-100" style="max-height: 300px; border-radius: 8px;" autoplay playsinline></video>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-5">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ translate('Recently Scanned') }}</h5>
                    <span class="badge badge-soft-warning">{{ $manifest->packages->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ translate('Barcode') }}</th>
                                    <th>{{ translate('Tracking') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Scanned By') }}</th>
                                    <th>{{ translate('Time') }}</th>
                                </tr>
                            </thead>
                            <tbody id="scanned-list">
                                @forelse($manifest->packages as $pkg)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td><code>{{ $pkg->barcode ?? '-' }}</code></td>
                                    <td><code>{{ $pkg->tracking_id }}</code></td>
                                    <td>
                                        @php $sMap = ['pending_review' => 'warning', 'pending' => 'secondary', 'ready_for_route' => 'info']; @endphp
                                        <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $pkg->status)) }}</span>
                                    </td>
                                    <td><small>{{ $pkg->scannedByUser?->name ?? $user->name }}</small></td>
                                    <td>{{ $pkg->scanned_at ? $pkg->scanned_at->format('h:i A') : '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">{{ translate('No packages scanned yet') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
