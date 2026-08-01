@extends('layouts.admin.app')

@section('title', translate('Delivery Man Preview'))

@push('css_or_js')
    <style>
        .driver-profile-overview,
        .driver-profile-overview > * {
            min-width: 0;
            max-width: 100%;
        }

        .driver-rating-summary .driver-rating-overview {
            flex: 0 0 145px;
        }

        .driver-rating-summary .driver-rating-distribution {
            min-width: 0;
        }

        .driver-rating-summary .driver-rating-distribution li {
            gap: .75rem;
        }

        .driver-rating-summary .progress-name {
            flex: 0 0 7rem;
            width: auto;
            margin-inline-end: 0;
        }

        .driver-rating-summary .progress {
            min-width: 3.75rem;
        }

        .driver-rating-summary .driver-rating-count {
            flex: 0 0 1.75rem;
        }

        .driver-rating-empty {
            max-width: 12rem;
            overflow-wrap: anywhere;
        }

        @media (min-width: 1200px) {
            .driver-rating-summary {
                flex: 0 1 34rem;
                width: auto !important;
                border-left: .0625rem solid #e7eaf3;
                padding-left: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .driver-rating-summary .driver-rating-overview {
                flex-basis: auto;
            }

            .driver-rating-summary .rating--review {
                padding-bottom: 0;
            }

            .driver-rating-summary .rating--review .title {
                font-size: 2rem;
                line-height: 1.15;
            }

            .driver-rating-summary .rating--review .title .out-of {
                font-size: 1.25rem;
                line-height: inherit;
            }

            .driver-rating-summary .progress-name {
                flex-basis: 5.5rem;
                font-size: .75rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid pb-0">
        @include('admin-views.delivery-man.partials._page_header')

        <div class="">
            @include('admin-views.delivery-man.partials._tab_menu')
        </div>
    </div>
    <!-- End Page Header -->

    <div class="content container-fluid pt-0">
        <div class="card">
            <div class="card-body pb-5">
                @if ($deliveryMan->application_status == 'approved')
                    <div
                        class="d-flex mb-xxl-4 mb-3 justify-content-between align-items-center gap-2 flex-wrap position-relative z-index-2">
                        <h4 class="card-title text-dark align-items-center flex-wrap gap-2">
                            {{ translate('messages.deliveryman Details') }}
                        </h4>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="javascript:"
                                class="btn request-alert py-2 {{ $deliveryMan->status ? 'btn--danger' : 'btn-success' }} align-items-center d-flex"
                                data-url="{{ route('admin.users.delivery-man.status', [$deliveryMan['id'], $deliveryMan->status ? 0 : 1]) }}"
                                data-message="{{ $deliveryMan->status ? translate('messages.you_want_to_suspend_this_deliveryman') : translate('messages.you_want_to_unsuspend_this_deliveryman') }}">
                                {{ $deliveryMan->status ? translate('messages.suspend_this_delivery_man') : translate('messages.unsuspend_this_delivery_man') }}
                            </a>
                            <div class="hs-unfold">

                                <div class="dropdown">
                                    <button class="btn btn--primary dropdown_after gap-0 fs-14 dropdown-toggle"
                                        type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                        aria-expanded="false">
                                        <img src="{{ asset('public/assets/admin/img/icons/bx_edit.png') }}" alt=""
                                            class="mr-1">
                                        {{ translate('Edit') }}

                                    </button>
                                    <div class="dropdown-menu min-w-220 dropdown-menu-right text-capitalize"
                                        aria-labelledby="dropdownMenuButton">
                                        <a class="dropdown-item fs-14 font-weight-medium text-dark"
                                            href="{{ route('admin.users.delivery-man.edit', [$deliveryMan->id]) }}">{{ translate('messages.Edit Information') }}</a>
                                        <a class="dropdown-item fs-14 font-weight-medium text-dark" data-toggle="modal"
                                            data-target="#work_switcher" href="javascript:">
                                            {{ translate('messages.Edit Delivery Type') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                @endif
                <div
                    class="driver-profile-overview d-flex flex-column flex-xl-row align-items-stretch align-items-md-center gap-3 border rounded p-3 overflow-hidden">
                    <div class="d-flex gap-3 justify-content-center position-relative w-115 rounded">
                        <img class="rounded" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                            src="{{ $deliveryMan['image_full_url'] }}" width="115" height="115"
                            alt="Delivery man image">
                        <span
                            class="suspend-badge bg-danger py-0 px-2 mb-2 fs-13 lh-1 text-white rounded position-absolute bottom-0 start-0">{{ !$deliveryMan['status'] && $deliveryMan['application_status'] == 'approved' ? translate('messages.suspended') : '' }}</span>
                    </div>

                    <div class="flex-grow-1 w-100">
                        <div class="mb-3">
                            <h4 title="{{ $deliveryMan['f_name'] . ' ' . $deliveryMan['l_name'] }}"
                                class="d-flex justify-content-center justify-content-md-start mb-1 gap-2">
                                {{ $deliveryMan['f_name'] . ' ' . $deliveryMan['l_name'] }}
                                @if ($deliveryMan->application_status == 'approved')
                                    @if ($deliveryMan['status'])
                                        @if ($deliveryMan['active'])
                                            <label
                                                class=" mb-0 badge badge-soft-primary">{{ translate('messages.online') }}</label>
                                        @else
                                            <label
                                                class=" mb-0 badge badge-soft-danger">{{ translate('messages.offline') }}</label>
                                        @endif
                                    @else
                                        <label
                                            class=" mb-0 badge badge-danger">{{ translate('messages.suspended') }}</label>
                                    @endif
                                @else
                                    <label
                                        class=" mb-0 badge badge-soft-{{ $deliveryMan->application_status == 'pending' ? 'info' : 'danger' }}">{{ translate('messages.' . $deliveryMan->application_status) }}</label>
                                @endif
                            </h4>
                            <div class="fs-12 text-title d-flex justify-content-center justify-content-md-start">
                                @if ($deliveryMan->application_status == 'approved')
                                    <a href="mailto:{{ $deliveryMan['email'] }}" class="text-title">
                                        {{ $deliveryMan['email'] }}</a>
                                    <span class="d-block mx-2 text-muted">|</span>
                                    <a href="tel:{{ $deliveryMan['phone'] }}" class="text-title">
                                        {{ $deliveryMan['phone'] }}</a>
                                @endif
                            </div>
                        </div>
                        <div
                            class="bg-light2 d-flex align-items-center flex-xxl-nowrap flex-wrap rider_overview-info rounded">
                            <div class="d-flex justify-content-center justify-content-md-start gap-3">
                                <div class="">
                                    <h6 class="fs-13 mb-1 font-weight-normal text-dark">
                                        {{ translate('messages.Job_Type') }} </h6>
                                    <p class="mb-0 fs-14 font-weight-bold text-dark ">
                                        {{ $deliveryMan->earning ? translate('messages.freelancer') : translate('messages.salary_based') }}
                                    </p>
                                </div>
                            </div>
                            <div class="text-muted line-30"></div>
                            <div class="d-flex justify-content-center justify-content-md-start gap-3">
                                <div class="">
                                    <h6 class="fs-13 mb-1 font-weight-normal text-dark">
                                        {{ translate('messages.Vehicle_Type') }}</h6>
                                    <p class="mb-0 fs-14 font-weight-bold text-dark ">
                                        {{ $deliveryMan?->vehicle?->type ?? translate('messages.Unknown Vehicle') }}</p>
                                </div>
                            </div>
                            <div class="text-muted line-30"></div>
                            <div class="d-flex justify-content-center justify-content-md-start gap-3">
                                <div class="">
                                    <h6 class="fs-13 mb-1 font-weight-normal text-dark">{{ translate('messages.Zone') }}
                                    </h6>
                                    <p class="mb-0 fs-14 font-weight-bold text-dark ">
                                        {{ isset($deliveryMan->zone) ? $deliveryMan->zone->name : translate('zone_deleted') }}
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                    @if ($deliveryMan->application_status == 'approved')
                        @include('admin-views.delivery-man.partials._rating_summary')
                    @endif
                </div>


                <div class="border rounded p-xxl-20 p-3 mt-20">
                    <div class="d-flex gap-2 align-items-center mb-20">
                        @if ($deliveryMan->application_status == 'approved')
                            <h5 class="mb-0 fs-16 fw-bold">{{ translate('Identity_Documents') }}</h5>
                        @else
                            <h5 class="mb-0 fs-16 fw-bold">{{ translate('Registration_Information') }}</h5>
                        @endif
                    </div>
                    <div class="row g-3">
                        @if ($deliveryMan->application_status == 'pending')
                            <div class="col-lg-4">
                                <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">

                                    <div class="key-val-list-item d-flex gap-3">
                                        <div class="text-title fs-14 identity__info">
                                            {{ translate('messages.First_Name') }} </div>:
                                        <div class="text-dark fs-14">{{ $deliveryMan['f_name'] }}</div>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-3">
                                        <div class="text-title fs-14 identity__info">{{ translate('messages.Last_Name') }}
                                        </div>:
                                        <div class="text-dark fs-14">{{ $deliveryMan['l_name'] }}</div>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-3">
                                        <div class="text-title fs-14 identity__info">{{ translate('messages.email') }}
                                        </div>:
                                        <div class="text-dark fs-14">{{ $deliveryMan['email'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="col-lg-4">
                            <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">

                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Identity_Type') }}</div>:
                                    <div class="text-dark fs-14">{{ translate($deliveryMan->identity_type) }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">
                                        {{ translate('messages.identification_number') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->identity_number }}</div>
                                </div>
                            </div>
                        </div>
                        @if ($deliveryMan->application_status == 'pending')
                            <div class="col-lg-4">
                                <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">

                                    <div class="key-val-list-item d-flex gap-3">
                                        <div class="text-title fs-14 identity__info">{{ translate('messages.Phone') }}
                                        </div>:
                                        <div class="text-dark fs-14">{{ $deliveryMan->phone }}</div>
                                    </div>
                                    <div class="key-val-list-item d-flex gap-3">
                                        <div class="text-title fs-14 identity__info">{{ translate('messages.Password') }}
                                        </div>:
                                        <div class="text-dark fs-14">**********</div>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class=" {{ $deliveryMan->application_status == 'pending' ? 'col-12' : 'col-lg-8' }} ">
                            <div class="bg-light2 rounded p-3 h-100 identity_documnet_body tabs-slide-wrap">

                                <div class="tabs-inner d-flex gap-3 identity_documnet_wrap">
                                    @foreach ($deliveryMan->identity_image_full_url as $key => $img)
                                        <button class="btn  p-0" data-toggle="modal"
                                            data-target="#image-{{ $key }}">
                                            <div class="gallary-card">
                                                <img class="rounded mx-h150 mx-w-100"
                                                    data-onerror-image="{{ asset('/public/assets/admin/img/900x400/img1.jpg') }}"
                                                    src="{{ $img }}" width="275" height="150"
                                                    alt="">
                                            </div>
                                        </button>
                                        <div class="modal fade" id="image-{{ $key }}" tabindex="-1"
                                            role="dialog" aria-labelledby="myModlabel" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title" id="myModlabel">
                                                            {{ translate('messages.Identity_Image') }}</h4>
                                                        <button type="button" class="close" data-dismiss="modal"><span
                                                                aria-hidden="true">&times;</span><span
                                                                class="sr-only">{{ translate('messages.Close') }}</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <img data-onerror-image="{{ asset('/public/assets/admin/img/900x400/img1.jpg') }}"
                                                            src="{{ $img }}" class="w-100 onerror-image">
                                                    </div>
                                                    <div class="modal-footer">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="arrow-area">
                                    <div class="button-prev align-items-center">
                                        <button type="button"
                                            class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-chevron-left fs-24"></i>
                                        </button>
                                    </div>
                                    <div class="button-next align-items-center">
                                        <button type="button"
                                            class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                            <i class="tio-chevron-right fs-24"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @if ($deliveryMan->application_status == 'approved')
    <div class="content container-fluid pt-0">
        <div class="card">
            <div class="card-body">
                <div class="d-flex gap-2 align-items-center mb-20">
                    <h5 class="mb-0 fs-16 fw-bold">{{ translate('messages.Vehicle & Trailer Details') }}</h5>
                </div>
                <div class="row g-3">
                    @php
                        $vehicleTypeLabels = [
                            'car' => 'Car', 'suv' => 'SUV', 'pickup_truck' => 'Pickup Truck',
                            'cargo_van' => 'Cargo Van', 'passenger_van' => 'Passenger Van',
                            'sprinter_van' => 'Sprinter Van', 'box_truck' => 'Box Truck',
                            'straight_truck' => 'Straight Truck', 'bicycle' => 'Bicycle',
                            'motorcycle' => 'Motorcycle', 'scooter_moped' => 'Scooter/Moped',
                            'tractor_trailer_18_wheeler' => 'Tractor Trailer / 18-Wheeler',
                            'flatbed_truck' => 'Flatbed Truck', 'tow_truck' => 'Tow Truck',
                            'refrigerated_truck' => 'Refrigerated Truck',
                            'other_commercial_vehicle' => 'Other Commercial Vehicle',
                        ];
                        $trailerTypeLabels = [
                            'utility' => 'Utility', 'enclosed' => 'Enclosed', 'flatbed' => 'Flatbed',
                            'car_hauler' => 'Car Hauler', 'gooseneck' => 'Gooseneck',
                            'fifth_wheel' => 'Fifth Wheel', 'step_deck' => 'Step Deck',
                            'lowboy' => 'Lowboy', 'refrigerated' => 'Refrigerated',
                            'dry_van' => 'Dry Van', 'other' => 'Other',
                        ];
                        $hitchTypeLabels = [
                            'ball' => 'Ball', 'pintle' => 'Pintle', 'gooseneck' => 'Gooseneck',
                            'fifth_wheel' => 'Fifth Wheel', 'bumper_pull' => 'Bumper Pull',
                            'pintle_hook' => 'Pintle Hook', 'other' => 'Other',
                        ];
                        $cdlClassLabels = ['A' => 'Class A', 'B' => 'Class B', 'C' => 'Class C', 'none' => 'None', 'not_applicable' => 'Not Applicable'];
                        $cdlStatusLabels = ['valid' => 'Valid', 'expired' => 'Expired', 'pending' => 'Pending', 'suspended' => 'Suspended', 'none' => 'None'];
                    @endphp
                    <div class="col-lg-4">
                        <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">
                            <div class="mb-1"><strong class="text-dark fs-14">{{ translate('messages.Vehicle_Type') }}</strong></div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Type') }}</div>:
                                <div class="text-dark fs-14 font-weight-bold">{{ $vehicleTypeLabels[$deliveryMan->vehicle_type] ?? $deliveryMan->vehicle_type ?? '-' }}</div>
                            </div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Registration Expiration') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->registration_expiration ? \Carbon\Carbon::parse($deliveryMan->registration_expiration)->format('M d, Y') : '-' }}</div>
                            </div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Insurance Expiration') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->insurance_expiration ? \Carbon\Carbon::parse($deliveryMan->insurance_expiration)->format('M d, Y') : '-' }}</div>
                            </div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Inspection Expiration') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->inspection_expiration ? \Carbon\Carbon::parse($deliveryMan->inspection_expiration)->format('M d, Y') : '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">
                            <div class="mb-1"><strong class="text-dark fs-14">{{ translate('Trailer Information') }}</strong></div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Has Trailer') }}</div>:
                                <div class="text-dark fs-14">
                                    @if ($deliveryMan->has_trailer)
                                        <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </div>
                            </div>
                            @if ($deliveryMan->has_trailer)
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Trailer Type') }}</div>:
                                    <div class="text-dark fs-14">{{ $trailerTypeLabels[$deliveryMan->trailer_type] ?? $deliveryMan->trailer_type ?? '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Length') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_length_feet ? $deliveryMan->trailer_length_feet . ' ft' : '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Width') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_width_feet ? $deliveryMan->trailer_width_feet . ' ft' : '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Capacity') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_capacity_lbs ? number_format($deliveryMan->trailer_capacity_lbs, 0) . ' lbs' : '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Hitch Type') }}</div>:
                                    <div class="text-dark fs-14">{{ $hitchTypeLabels[$deliveryMan->hitch_type] ?? $deliveryMan->hitch_type ?? '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Plate Number') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_plate_number ?? '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Trailer Registration') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_registration_expiration ? \Carbon\Carbon::parse($deliveryMan->trailer_registration_expiration)->format('M d, Y') : '-' }}</div>
                                </div>
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Trailer Insurance') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->trailer_insurance_expiration ? \Carbon\Carbon::parse($deliveryMan->trailer_insurance_expiration)->format('M d, Y') : '-' }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">
                            <div class="mb-1"><strong class="text-dark fs-14">{{ translate('Commercial Credentials') }}</strong></div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('CDL Status') }}</div>:
                                <div class="text-dark fs-14">
                                    @if ($deliveryMan->cdl_status && $deliveryMan->cdl_status !== 'none')
                                        <span class="badge badge-soft-{{ $deliveryMan->cdl_status === 'valid' ? 'success' : ($deliveryMan->cdl_status === 'expired' ? 'warning' : 'danger') }}">
                                            {{ $cdlStatusLabels[$deliveryMan->cdl_status] ?? $deliveryMan->cdl_status }}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-secondary">None</span>
                                    @endif
                                </div>
                            </div>
                            @if ($deliveryMan->cdl_class && $deliveryMan->cdl_class !== 'none')
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('CDL Class') }}</div>:
                                    <div class="text-dark fs-14">{{ $cdlClassLabels[$deliveryMan->cdl_class] ?? $deliveryMan->cdl_class }}</div>
                                </div>
                            @endif
                            @if ($deliveryMan->cdl_number)
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('CDL Number') }}</div>:
                                    <div class="text-dark fs-14">{{ $deliveryMan->cdl_number }}</div>
                                </div>
                            @endif
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('DOT Number') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->dot_number ?? '-' }}</div>
                            </div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('MC Number') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->mc_number ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="bg-light2 rounded p-3 h-100 d-flex flex-column gap-2">
                            <div class="mb-1"><strong class="text-dark fs-14">{{ translate('Capabilities') }}</strong></div>
                            <div class="d-flex flex-wrap gap-2">
                                @if ($deliveryMan->has_liftgate)
                                    <span class="badge badge-soft-primary">{{ translate('Liftgate') }}</span>
                                @endif
                                @if ($deliveryMan->has_pallet_jack)
                                    <span class="badge badge-soft-primary">{{ translate('Pallet Jack') }}</span>
                                @endif
                                @if ($deliveryMan->has_hazmat)
                                    <span class="badge badge-soft-danger">{{ translate('Hazmat') }}</span>
                                @endif
                                @if ($deliveryMan->has_medical_courier_training)
                                    <span class="badge badge-soft-success">{{ translate('Medical Courier') }}</span>
                                @endif
                                @if ($deliveryMan->has_cargo_insurance)
                                    <span class="badge badge-soft-info">{{ translate('Cargo Insurance') }}</span>
                                @endif
                                @if ($deliveryMan->has_cooler_bag)
                                    <span class="badge badge-soft-info">{{ translate('Cooler Bag') }}</span>
                                @endif
                                @if ($deliveryMan->has_cargo_space)
                                    <span class="badge badge-soft-info">{{ translate('Cargo Space') }}</span>
                                @endif
                            </div>
                            <div class="key-val-list-item d-flex gap-3 mt-2">
                                <div class="text-title fs-14 identity__info">{{ translate('Max Payload') }}</div>:
                                <div class="text-dark fs-14">{{ $deliveryMan->max_payload_lbs ? number_format($deliveryMan->max_payload_lbs, 0) . ' lbs' : '-' }}</div>
                            </div>
                            <div class="key-val-list-item d-flex gap-3">
                                <div class="text-title fs-14 identity__info">{{ translate('Cargo Dimensions') }}</div>:
                                <div class="text-dark fs-14">
                                    @if ($deliveryMan->cargo_length_inches && $deliveryMan->cargo_width_inches && $deliveryMan->cargo_height_inches)
                                        {{ number_format($deliveryMan->cargo_length_inches, 0) }}L x {{ number_format($deliveryMan->cargo_width_inches, 0) }}W x {{ number_format($deliveryMan->cargo_height_inches, 0) }}H in
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                            @if ($deliveryMan->cargo_insurance_expiration)
                                <div class="key-val-list-item d-flex gap-3">
                                    <div class="text-title fs-14 identity__info">{{ translate('Cargo Insurance Exp.') }}</div>:
                                    <div class="text-dark fs-14">{{ \Carbon\Carbon::parse($deliveryMan->cargo_insurance_expiration)->format('M d, Y') }}</div>
                                </div>
                            @endif
                            @if (count($deliveryMan->capability_tags ?? []) > 0)
                                <div class="mt-2">
                                    <div class="text-title fs-14 identity__info mb-1">{{ translate('Tags') }}</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($deliveryMan->capability_tags as $tag)
                                            <span class="badge badge-soft-dark">{{ ucwords(str_replace('_', ' ', $tag)) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @if (count($deliveryMan->preferred_work_types ?? []) > 0)
                                <div class="mt-2">
                                    <div class="text-title fs-14 identity__info mb-1">{{ translate('Preferred Work Types') }}</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($deliveryMan->preferred_work_types as $wt)
                                            <span class="badge badge-soft-primary">{{ ucwords(str_replace('_', ' ', $wt)) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="content container-fluid pt-0">
        <div class="card">
            <div class="card-body">
                @if ($deliveryMan->application_status == 'approved')
                    <div class="row g-3 color-card-custom">
                        <div class="col-lg-3">
                            <div class="color-card h-100 align-items-center justify-content-center">
                                <div
                                    class="box d-flex flex-column text-center justify-content-center align-items-center gap-3">
                                    <div class="img-box">
                                        <img class="resturant-icon w--30"
                                            src="{{ asset('public/assets/admin/img/icons/color-icon-1.png') }}"
                                            alt="img">
                                    </div>
                                    <div>
                                        <h2 class="title fs-24 fw-bold mb-1">
                                            {{ count($deliveryMan['order_transaction']) }}
                                        </h2>
                                        <div class="subtitle text-title">
                                            {{ translate('messages.total_delivered_orders') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-9">
                            <div class="row g-3 row-3">


                                <!-- Collected Cash Card Example -->
                                <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                    <div class="color-card color-2">
                                        <div class="img-box">
                                            <img class="resturant-icon w--30"
                                                src="{{ asset('/public/assets/admin/img/icons/color-icon-2.png') }}"
                                                alt="transactions">
                                        </div>
                                        <div>
                                            <h2 class="title fs-24 fw-bold mb-1">
                                                {{ \App\CentralLogics\Helpers::format_currency($deliveryMan->wallet ? $deliveryMan->wallet->collected_cash : 0.0) }}
                                            </h2>
                                            <div class="subtitle text-title">
                                                {{ translate('messages.cash_in_hand') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Earning Card Example -->
                                <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                    <div class="color-card color-3">
                                        <div class="img-box">
                                            <img class="resturant-icon w--30"
                                                src="{{ asset('/public/assets/admin/img/icons/color-icon-3.png') }}"
                                                alt="transactions">
                                        </div>
                                        <div>
                                            <h2 class="title fs-24 fw-bold mb-1">
                                                {{ \App\CentralLogics\Helpers::format_currency($deliveryMan->wallet ? $deliveryMan->wallet->total_earning : 0.0) }}
                                            </h2>
                                            <div class="subtitle text-title">
                                                {{ translate('messages.total_earning') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Total Earning Card Example -->

                                <?php
                                $balance = 0;
                                if ($deliveryMan->wallet) {
                                    $balance = $deliveryMan->wallet->total_earning - ($deliveryMan->wallet->total_withdrawn + $deliveryMan->wallet->pending_withdraw + $deliveryMan->wallet->collected_cash);
                                }

                                ?>
                                @if ($deliveryMan->earning)
                                    @if ($balance > 0)
                                        <div class="col-sm-6 col-lg-4">
                                            <div class="color-card colxxl-4">
                                                <div class="img-box">
                                                    <img class="resturant-icon w--30"
                                                        src="{{ asset('/public/assets/admin/img/icons/group.png') }}"
                                                        alt="transactions">
                                                </div>
                                                <div>
                                                    <h2 class="title fs-24 fw-bold mb-1">
                                                        {{ \App\CentralLogics\Helpers::format_currency(abs($balance)) }}
                                                    </h2>
                                                    <div class="subtitle text-title">
                                                        {{ translate('messages.Withdraw_Able_Balance') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($balance < 0)
                                        <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                            <div class="color-card color-4">
                                                <div class="img-box">
                                                    <img class="resturant-icon w--30"
                                                        src="{{ asset('/public/assets/admin/img/icons/color-icon-4.png') }}"
                                                        alt="transactions">
                                                </div>
                                                <div>
                                                    <h2 class="title fs-24 fw-bold mb-1">
                                                        {{ \App\CentralLogics\Helpers::format_currency(abs($deliveryMan->wallet->collected_cash)) }}
                                                    </h2>
                                                    <div class="subtitle text-title">
                                                        {{ translate('messages.Payable_Balance') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                            <div class="color-card color-4">
                                                <div class="img-box">
                                                    <img class="resturant-icon w--30"
                                                        src="{{ asset('/public/assets/admin/img/icons/group.png') }}"
                                                        alt="transactions">
                                                </div>
                                                <div>
                                                    <h2 class="title fs-24 fw-bold mb-1">
                                                        {{ \App\CentralLogics\Helpers::format_currency(0) }}
                                                    </h2>
                                                    <div class="subtitle text-title">
                                                        {{ translate('messages.Balance') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif


                                    <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                        <div class="color-card color-5">
                                            <div class="img-box">
                                                <img class="resturant-icon w--30"
                                                    src="{{ asset('/public/assets/admin/img/icons/color-icon-5.png') }}"
                                                    alt="transactions">
                                            </div>
                                            <div>
                                                <h2 class="title fs-24 fw-bold mb-1">
                                                    {{ \App\CentralLogics\Helpers::format_currency($deliveryMan->wallet ? $deliveryMan->wallet->total_withdrawn : 0.0) }}
                                                </h2>
                                                <div class="subtitle text-title">
                                                    {{ translate('messages.Total_withdrawn') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                        <div class="color-card color-6">
                                            <div class="img-box">
                                                <img class="resturant-icon w--30"
                                                    src="{{ asset('/public/assets/admin/img/icons/color-icon-6.png') }}"
                                                    alt="transactions">
                                            </div>
                                            <div>
                                                <h2 class="title fs-24 fw-bold mb-1">
                                                    {{ \App\CentralLogics\Helpers::format_currency($deliveryMan->wallet ? $deliveryMan->wallet->pending_withdraw : 0.0) }}
                                                </h2>
                                                <div class="subtitle text-title">
                                                    {{ translate('messages.Pending_withdraw') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-xxl-4 col-xl-6 col-lg-6">
                                        <div class="color-card color-9">
                                            <div class="img-box">
                                                <img class="resturant-icon w--30"
                                                    src="{{ asset('/public/assets/admin/img/icons/loyalty-star.png') }}"
                                                    alt="transactions">
                                            </div>
                                            <div>
                                                <h2 class="title text--039D55 fs-24 fw-bold mb-1">
                                                    {{ (int) $deliveryMan->loyalty_point }}
                                                </h2>
                                                <div class="subtitle text-title">
                                                    {{ translate('messages.Loyalty Point') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>


    @if ($deliveryMan->application_status == 'approved')
        <div class="content container-fluid pt-0">
            <div class="card">
                <!-- Header -->
                <div class="card-header flex-sm-nowrap flex-wrap gap-2 pt-3 pb-0 border-0">
                    <h5 class="card-header-title d-flex align-items-center gap-2 text-nowrap line--limite-1">
                        {{ translate('messages.review_list') }}
                        <span class="badge badge-soft-dark ml-2" id="itemCount">
                            {{ $reviews->total() }}
                        </span>
                    </h5>
                    <div class="search--button-wrapper justify-content-end">
                        <form class="search-form min--260">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                    placeholder="{{ translate('messages.search here') }}"
                                    value="{{ request()->search }}" aria-label="Search" tabindex="1">

                                <button type="submit" class="btn btn--secondary bg-modal-btn"><i
                                        class="tio-search text-muted"></i></button>
                            </div>
                        </form>
                        <!-- Unfold -->
                        <div class="hs-unfold mr-2">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                                <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                            </a>

                            <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{ route('admin.users.delivery-man.review-export', ['type' => 'excel', 'id' => $deliveryMan->id, request()->getQueryString()]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                        alt="Image Description">
                                    {{ translate('messages.excel') }}
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{ route('admin.users.delivery-man.review-export', ['type' => 'csv', 'id' => $deliveryMan->id, request()->getQueryString()]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="Image Description">
                                    {{ translate('messages.csv') }}
                                </a>
                            </div>
                        </div>
                        <!-- End Unfold -->
                    </div>
                </div>
                <!-- End Header -->

                <!-- New Table -->

                <div class="p-xxl-20 p-3">
                    <div class="card-body shadow-sm rounded p-0">
                        <div class="table-responsive datatable-custom">
                            <table id="datatable" class="table table-border table-thead-bordered table-nowrap card-table"
                                data-hs-datatables-options='{
                            "columnDefs": [{
                                "targets": [0, 3, 6],
                                "orderable": false
                            }],
                            "order": [],
                            "info": {
                            "totalQty": "#datatableWithPaginationInfoTotalQty"
                            },
                            "search": "#datatableSearch",
                            "entries": "#datatableEntries",
                            "pageLength": 25,
                            "isResponsive": false,
                            "isShowPaging": false,
                            "pagination": "datatablePagination"
                        }'>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-0 fs-14">{{ translate('messages.SL') }}</th>
                                        <th class="border-0 fs-14">{{ translate('messages.order_ID') }}</th>
                                        <th class="border-0 fs-14">{{ translate('messages.customer') }}</th>
                                        <th class="border-0 fs-14">{{ translate('messages.Rating') }}</th>
                                        <th class="border-0 fs-14">{{ translate('messages.Review ID') }}</th>
                                        <th class="border-0 fs-14">{{ translate('messages.review') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($reviews as $k => $review)
                                        <tr>
                                            <td class="fs-14 text-dark">{{ $k + $reviews->firstItem() }}</td>
                                            <td>
                                                <a class="line--limit-1 fs-14 text-dark max-w--220px min-w-135px text-wrap"
                                                    href="{{ route('admin.order.all-details', ['id' => $review->order_id]) }}">{{ $review->order_id }}</a>
                                            </td>
                                            <td>
                                                @if ($review->customer)
                                                    <a class="d-flex align-items-center"
                                                        href="{{ route('admin.customer.view', [$review['user_id']]) }}">
                                                        <span
                                                            class="text-dark fs-14 line--limit-1 max-w--220px min-w-135px text-wrap">
                                                            {{ $review->customer ? $review->customer['f_name'] . ' ' . $review->customer['l_name'] : '' }}
                                                        </span>
                                                    </a>
                                                @else
                                                    {{ translate('messages.customer_not_found') }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="">
                                                    <div class="d-flex gap-1 align-items-center">
                                                        <span class="d-inline-block mt-half">{{ $review->rating }}</span>
                                                        <i class="tio-star text-warning"></i>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    class="text-dark fs-14 line--limit-1 max-w--220px min-w-135px text-wrap">
                                                    {{ $review->id }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fs-14 line--limit-2 max-w-390 min-w-220 text-dark text-wrap">
                                                    {{ $review['comment'] }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- End Table -->
                        @if (count($reviews) !== 0)
                            <hr>
                        @endif
                        <div class="page-area">
                            {!! $reviews->links() !!}
                        </div>
                        @if (count($reviews) === 0)
                            <div class="empty--data">
                                <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}"
                                    alt="public">
                                <h5>
                                    {{ translate('no_data_found') }}
                                </h5>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif

    </div>


    <div class="modal fade" id="work_switcher">
        <div class="modal-dialog modal-dialog-centered max-w-500px">
            <div class="modal-content">
                <div class="modal-header pr-3">
                    <button type="button" class="close border bg-modal-btn rounded-circle" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear text-light-gray"></span>
                    </button>
                </div>
                <div class="modal-body px-sm-4 px-3 pb-5 pt-0">
                    <div class="text-center">
                        <div>
                            <div class="text-center mb-20">
                                <img width="80"
                                    src="{{ asset('public/assets/admin/img/icons/deliveryman-type.png') }}"
                                    class="">
                                <h5 class="modal-title m-0"></h5>
                            </div>
                            <div class="text-center mb-4">
                                <h3 class="font-weight-normal text-dark">
                                    {{ translate('This deliveryman is currently on') }} <br>
                                    <strong>{{ $deliveryMan->earning ? translate('messages.freelancer') : translate('messages.salary_based') }}</strong>
                                </h3>
                            </div>
                        </div>
                        <div class="bg-light2 rounded p-sm-4 p-3">
                            <p class="fs-14 mb-20 text-body">{{ translate('Do you want to change the delivery type?') }}
                            </p>
                            <div class="btn--container justify-content-center p-0">
                                <a href="{{ route('admin.users.delivery-man.earning', ['id' => $deliveryMan->id, 'status' => $deliveryMan->earning ? 0 : 1]) }}"
                                    class="btn btn--primary min-w-120">
                                    {{ $deliveryMan->earning ? translate('Switch to Salary Based') : translate('Switch to Freelanced Based') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";
        $('.request-alert').on('click', function() {
            let url = $(this).data('url');
            let message = $(this).data('message');
            request_alert(url, message);
        })

        function request_alert(url, message) {
            Swal.fire({
                title: '{{ translate('messages.are_you_sure') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('messages.no') }}',
                confirmButtonText: '{{ translate('messages.yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            })
        }
    </script>
@endpush
