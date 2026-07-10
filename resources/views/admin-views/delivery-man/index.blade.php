@extends('layouts.admin.app')

@section('title', translate('messages.Add new delivery-man'))


@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-15 mt-2">
            <h1 class="page-header-title mb-0 fs-24 text-break">
                <!-- <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/delivery-man.png') }}" class="w--26" alt="">
                </span> -->
                <span>{{ translate('messages.add_new_deliveryman') }}</span>
            </h1>
        </div>
        <!-- End Page Header -->
        <form class="validate-form global-ajax-form" action="{{ route('admin.users.delivery-man.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="serve_for[]" value="delivery">
            <div class="card mb-20">
                <div class="card-header">
                   <div>
                        <h3 class="mb-1">
                            {{ translate('Basic Information') }}
                        </h3>
                        <p class="mb-0 fs-12">
                            {{ translate('Here you setup your all business information.') }}
                        </p>
                   </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="shadow-sm p-xxl-20 p-3 bg-white h-100">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.first_name') }}
                                                <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span>

                                            </label>
                                            <input type="text" name="f_name" class="form-control"
                                                placeholder="{{ translate('messages.Ex : Jhone') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.last_name') }}
                                               <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span>

                                            </label>
                                            <input type="text" name="l_name" class="form-control"
                                                placeholder="{{ translate('messages.Ex : doe') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.email') }}
                                                <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span>

                                            </label>
                                            <input type="email" name="email" class="form-control"
                                                placeholder="{{ translate('messages.Ex:') }} ex@example.com" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.deliveryman_type') }}
                                                <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span>
                                            </label>
                                            <select name="earning" data-placeholder="{{ translate('messages.Select_deliveryman_type') }}" required class="form-control js-select2-custom">
                                                <option value="" readonly="true" hidden="true" > {{ translate('messages.Select_deliveryman_type') }}</option>
                                                <option value="1">{{ translate('messages.freelancer') }}</option>
                                                <option value="0">{{ translate('messages.salary_based') }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-6" id="ref_code" style="display: none;">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.referral_code') }}
                                            </label>
                                            <input type="text" name="referral_code"  id="referral_code" class="form-control __form-control"
                                                placeholder="{{ translate('messages.Ex: STAKXPFIDK') }}"
                                                value="">
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.Select zone') }} <span
                                                    class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                    <span class="input-label-secondary text--title" data-toggle="tooltip" data-placement="right" data-original-title="Content need ...">
                                                        <i class="tio-info text-muted"></i>
                                                    </span>
                                                </span>
                                            </label>
                                            <select name="zone_id" class="form-control js-select2-custom" required
                                                data-placeholder="{{ translate('messages.select_zone') }}">
                                                <option value="" readonly="true" hidden="true">
                                                    {{ translate('messages.select_zone') }}</option>
                                                @foreach (\App\Models\Zone::all() as $zone)
                                                    @if (isset(auth('admin')->user()->zone_id))
                                                        @if (auth('admin')->user()->zone_id == $zone->id)
                                                            <option value="{{ $zone->id }}" selected>{{ $zone->name }}
                                                            </option>
                                                        @endif
                                                    @else
                                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group m-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.Select Vehicle') }} <span
                                                    class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span>
                                            </label>
                                            <select name="vehicle_id" class="form-control js-select2-custom h--45px" required
                                                data-placeholder="{{ translate('messages.select_vehicle') }}">
                                                <option value="" readonly="true" hidden="true"> {{ translate('messages.select_vehicle') }}</option>
                                                @foreach (\App\Models\DMVehicle::where('status', 1)->get(['id', 'type']) as $v)
                                                    <option value="{{ $v->id }}">{{ $v->type }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="bg-light2 rounded h-100 d-center">
                                <div class="text-center">
                                    <div class="mb-1">
                                        <h4 class="mb-1">{{ translate('Deliveryman image') }} <span class="text-danger">*</span></h4>
                                    </div>
                                    <div class="mx-auto text-center">
                                        @include('admin-views.partials._image-uploader', [
                                                'id' => 'image-input',
                                                'name' => 'image',
                                                'ratio' => '1:1',
                                                'isRequired' => false,
                                                'existingImage' => null,
                                                'imageExtension' => IMAGE_EXTENSION,
                                                'imageFormat' => IMAGE_FORMAT,
                                                'maxSize' => MAX_FILE_SIZE,
                                                ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                   <div>
                        <h3 class="mb-1">
                            {{ translate('General Setup') }}
                        </h3>
                        <p class="mb-0 fs-12">
                            {{ translate('Here you can manage time settings to match with your business criteria') }}
                        </p>
                   </div>
                </div>
                <div class="card-body">
                    <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20">
                        <div class="mb-20">
                            <h4 class="mb-1">
                                {{ translate('Identity Info') }}
                            </h4>
                            <p class="mb-0 fs-12">
                                {{ translate('Setup your business time zone and format from here') }}
                            </p>
                        </div>
                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3 mb-20">
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.identity_type') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <select required name="identity_type" data-placeholder="{{ translate('messages.select_identity_type') }}" class="form-control js-select2-custom">
                                            <option  value="" readonly="true" hidden="true"  > {{ translate('messages.select_identity_type') }}</option>
                                            <option value="passport">{{ translate('messages.passport') }}</option>
                                            <option value="driving_license">{{ translate('messages.driving_license') }} </option>
                                            <option value="nid">{{ translate('messages.nid') }}</option>
                                            <option value="store_id">{{ translate('messages.store_id') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-lg-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.identity_number') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="text" name="identity_number" class="form-control"
                                            placeholder="{{ translate('messages.Ex:') }} DH-23434-LS" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3">
                            <div class="mb-0">
                                <h4 class="mb-1">
                                    {{ translate('Identity Image') }} <span class="text-danger">*</span>
                                </h4>
                                <p class="mb-0 fs-12">
                                    {{ translate(' Jpg, jpeg, png, gif, webp. Less Than 2MB') }} <span class="text-dark">(2:1)</span>
                                </p>
                            </div>
                            <div class="form-group m-0">
                                <div class="identity_documnet_body multiple_coba-img tabs-slide-wrap position-relative">
                                    <div class="tabs-inner pt-3 d-flex gap-3 identity_documnet_wrap" id="coba"></div>
                                    <div class="arrow-area">
                                        <div class="button-prev align-items-center">
                                            <button type="button"
                                                class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                                <i class="tio-chevron-left fs-24"></i>
                                            </button>
                                        </div>
                                        <div class="button-next align-items-center pt-5">
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
                    <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white">
                        <div class="mb-20">
                            <h4 class="mb-1">
                                {{ translate('Account Information') }}
                            </h4>
                            <p class="mb-0 fs-12">
                                {{ translate('Setup your business time zone and format from here') }}
                            </p>
                        </div>
                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3">
                            <div class="row g-3">
                                <div class="col-md-4 col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('messages.phone') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <input type="tel" id="phone" name="phone" class="form-control"
                                            placeholder="{{ translate('messages.Ex:') }} 017********" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="js-form-message form-group mb-0">
                                        <label class="input-label"
                                            for="signupSrPassword">{{ translate('messages.password') }}<span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"><i class="tio-info text-muted"></i></span> <span
                                                    class="form-label-secondary text-danger" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.') }}"> *
                                                </span></label>

                                        <div class="input-group input-group-merge">
                                            <input type="password" class="js-toggle-password form-control" name="password"
                                                id="signupSrPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                                title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"
                                                placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                                aria-label="8+ characters required" required
                                                data-msg="Your password is invalid. Please try again."
                                                data-hs-toggle-password-options='{
                                            "target": [".js-toggle-password-target-1"],
                                            "defaultClass": "tio-hidden-outlined",
                                            "showClass": "tio-visible-outlined",
                                            "classChangeTarget": ".js-toggle-passowrd-show-icon-1"
                                            }'>
                                            <div class="js-toggle-password-target-1 input-group-append">
                                                <a class="input-group-text" href="javascript:;">
                                                    <i class="js-toggle-passowrd-show-icon-1 tio-visible-outlined"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 col-12">
                                    <div class="js-form-message form-group mb-0">
                                        <label class="input-label"
                                            for="signupSrConfirmPassword">{{ translate('messages.confirm_password') }}<span
                                                class="form-label-secondary text-danger" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('messages.Required.') }}"> *
                                            </span>
                                        </label>
                                        <div class="input-group input-group-merge">
                                            <input type="password" class="js-toggle-password form-control" name="confirmPassword"
                                                id="signupSrConfirmPassword" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                                title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"
                                                placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                                aria-label="8+ characters required" required
                                                data-msg="Password does not match the confirm password."
                                                data-hs-toggle-password-options='{
                                                "target": [".js-toggle-password-target-2"],
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": ".js-toggle-passowrd-show-icon-2"
                                                }'>
                                            <div class="js-toggle-password-target-2 input-group-append">
                                                <a class="input-group-text" href="javascript:;">
                                                    <i class="js-toggle-passowrd-show-icon-2 tio-visible-outlined"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-20">
                <div class="card-header">
                   <div>
                        <h3 class="mb-1">
                            {{ translate('Vehicle, Trailer & Capability Settings') }}
                        </h3>
                        <p class="mb-0 fs-12">
                            {{ translate('Configure vehicle type, trailer details, commercial credentials, and cargo capabilities.') }}
                        </p>
                   </div>
                </div>
                <div class="card-body">
                    @php
                        $vehicleTypes = [
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
                        $trailerTypes = [
                            'utility' => 'Utility', 'enclosed' => 'Enclosed', 'flatbed' => 'Flatbed',
                            'car_hauler' => 'Car Hauler', 'gooseneck' => 'Gooseneck',
                            'fifth_wheel' => 'Fifth Wheel', 'step_deck' => 'Step Deck',
                            'lowboy' => 'Lowboy', 'refrigerated' => 'Refrigerated',
                            'dry_van' => 'Dry Van', 'other' => 'Other',
                        ];
                        $hitchTypes = [
                            'ball' => 'Ball', 'pintle' => 'Pintle', 'gooseneck' => 'Gooseneck',
                            'fifth_wheel' => 'Fifth Wheel', 'bumper_pull' => 'Bumper Pull',
                            'pintle_hook' => 'Pintle Hook', 'other' => 'Other',
                        ];
                        $cdlClasses = ['A' => 'Class A', 'B' => 'Class B', 'C' => 'Class C', 'none' => 'None', 'not_applicable' => 'Not Applicable'];
                        $cdlStatuses = ['none' => 'None/Not Required', 'valid' => 'Valid', 'expired' => 'Expired', 'pending' => 'Pending', 'suspended' => 'Suspended'];
                    @endphp

                    <div class="shadow-sm p-xxl-20 p-xl-3 p-2 bg-white mb-20">
                        <div class="mb-20">
                            <h4 class="mb-1">{{ translate('Vehicle & Trailer Details') }}</h4>
                        </div>
                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3 mb-20">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('messages.Vehicle_Type') }} <span class="text-danger">*</span></label>
                                        <select name="vehicle_type" class="form-control js-select2-custom">
                                            <option value="" readonly="true" hidden="true">{{ translate('messages.select_vehicle') }}</option>
                                            @foreach($vehicleTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Registration Expiration') }}</label>
                                        <input type="date" name="registration_expiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Insurance Expiration') }}</label>
                                        <input type="date" name="insurance_expiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Inspection Expiration') }}</label>
                                        <input type="date" name="inspection_expiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('messages.Has Trailer') }}</label>
                                        <select name="has_trailer" class="form-control js-select2-custom trailer-toggle">
                                            <option value="0" selected>{{ translate('messages.No') }}</option>
                                            <option value="1">{{ translate('messages.Yes') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3 mb-20 trailer-fields" style="display:none">
                            <h5 class="mb-3">{{ translate('Trailer Information') }}</h5>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Trailer Type') }}</label>
                                        <select name="trailer_type" class="form-control js-select2-custom">
                                            <option value="" readonly="true" hidden="true">{{ translate('messages.select_vehicle') }}</option>
                                            @foreach($trailerTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Hitch Type') }}</label>
                                        <select name="hitch_type" class="form-control js-select2-custom">
                                            <option value="" readonly="true" hidden="true">{{ translate('messages.select_vehicle') }}</option>
                                            @foreach($hitchTypes as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Length (ft)') }}</label>
                                        <input type="number" step="0.1" name="trailer_length_feet" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Width (ft)') }}</label>
                                        <input type="number" step="0.1" name="trailer_width_feet" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Capacity (lbs)') }}</label>
                                        <input type="number" step="0.1" name="trailer_capacity_lbs" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Plate Number') }}</label>
                                        <input type="text" name="trailer_plate_number" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Trailer Registration') }}</label>
                                        <input type="date" name="trailer_registration_expiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Trailer Insurance') }}</label>
                                        <input type="date" name="trailer_insurance_expiration" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3 mb-20">
                            <h5 class="mb-3">{{ translate('Commercial Credentials') }}</h5>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('CDL Status') }}</label>
                                        <select name="cdl_status" class="form-control js-select2-custom cdl-status-toggle">
                                            @foreach($cdlStatuses as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4 cdl-class-field" style="display:none">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('CDL Class') }}</label>
                                        <select name="cdl_class" class="form-control js-select2-custom">
                                            @foreach($cdlClasses as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4 cdl-number-field" style="display:none">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('CDL Number') }}</label>
                                        <input type="text" name="cdl_number" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('DOT Number') }}</label>
                                        <input type="text" name="dot_number" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('MC Number') }}</label>
                                        <input type="text" name="mc_number" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light2 rounded p-xxl-20 p-xl-3 p-3">
                            <h5 class="mb-3">{{ translate('Capabilities & Cargo') }}</h5>
                            <div class="row g-3">
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Pallet Jack') }}</label>
                                        <select name="has_pallet_jack" class="form-control js-select2-custom">
                                            <option value="0" selected>{{ translate('messages.No') }}</option>
                                            <option value="1">{{ translate('messages.Yes') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Hazmat Certified') }}</label>
                                        <select name="has_hazmat" class="form-control js-select2-custom">
                                            <option value="0" selected>{{ translate('messages.No') }}</option>
                                            <option value="1">{{ translate('messages.Yes') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Cargo Insurance') }}</label>
                                        <select name="has_cargo_insurance" class="form-control js-select2-custom cargo-insurance-toggle">
                                            <option value="0" selected>{{ translate('messages.No') }}</option>
                                            <option value="1">{{ translate('messages.Yes') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3 cargo-insurance-date" style="display:none">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Cargo Insurance Exp.') }}</label>
                                        <input type="date" name="cargo_insurance_expiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Max Payload (lbs)') }}</label>
                                        <input type="number" step="0.1" name="max_payload_lbs" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Cargo Length (in)') }}</label>
                                        <input type="number" step="0.1" name="cargo_length_inches" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Cargo Width (in)') }}</label>
                                        <input type="number" step="0.1" name="cargo_width_inches" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group mb-0">
                                        <label class="input-label">{{ translate('Cargo Height (in)') }}</label>
                                        <input type="number" step="0.1" name="cargo_height_inches" class="form-control" placeholder="0.0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="btn--container justify-content-end mt-20">
                <button type="reset" id="reset_btn"
                    class="btn btn--reset min-w-120px">{{ translate('messages.reset') }}</button>
                <button type="submit" class="btn btn--primary min-w-120px"><i class="tio-save"></i> {{translate('messages.Save Information')}}</button>
            </div>
        </form>
    </div>

@endsection

@push('script_2')

    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script>
        "use strict";


        $(function() {
            $("#coba").spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '120px',
                groupClassName: 'spartan_item_wrapper size--md',
                maxFileSize: {{ MAX_FILE_SIZE }} * 1024 * 1024,
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/400x400/coba-placeholder.png') }}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function(index, file) {

                },
                onRenderedPreview: function(index) {

                },
                onRemoveRow: function(index) {

                },
                onExtensionErr: function(index, file) {
                    toastr.error(
                    '{{ translate('messages.please_only_input_png_or_jpg_type_file') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function(index, file) {
                    toastr.error('{{ translate('messages.file_size_too_big') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });

        $('#reset_btn').click(function() {
            $('#viewer').attr('src', '{{ asset('public/assets/admin/img/400x400/img2.jpg') }}');
            $("#coba").empty().spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '120px',
                groupClassName: 'col-6 spartan_item_wrapper size--md',
                maxFileSize: '',
                placeholderImage: {
                    image: '{{ asset('public/assets/admin/img/400x400/img2.jpg') }}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function(index, file) {

                },
                onRenderedPreview: function(index) {

                },
                onRemoveRow: function(index) {

                },
                onExtensionErr: function(index, file) {
                    toastr.error(
                    '{{ translate('messages.please_only_input_png_or_jpg_type_file') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function(index, file) {
                    toastr.error('{{ translate('messages.file_size_too_big') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        })
        $('select[name="earning"]').on('change', function() {
            if (this.value === '1') {
                $('#ref_code').show();
            } else {
                $('#ref_code').hide();
                $('#referral_code').val('');
            }
        });

        $(document).ready(function() {
            if ($('select[name="earning"]').val() === '1') {
                $('#ref_code').show();
            } else {
                $('#ref_code').hide();
                $('#referral_code').val('');
            }
        });

        $('.trailer-toggle').on('change', function() {
            if ($(this).val() === '1') { $('.trailer-fields').show(); }
            else { $('.trailer-fields').hide(); }
        });

        $('.cdl-status-toggle').on('change', function() {
            if ($(this).val() === 'valid') { $('.cdl-class-field, .cdl-number-field').show(); }
            else { $('.cdl-class-field, .cdl-number-field').hide(); }
        });

        $('.cargo-insurance-toggle').on('change', function() {
            if ($(this).val() === '1') { $('.cargo-insurance-date').show(); }
            else { $('.cargo-insurance-date').hide(); }
        });

        $(document).on('submit', 'form', function (e) {
    let identity_image = 0;

    $(this).find('input[name="identity_image[]"]').each(function () {
        if ($(this).val()) identity_image++;
    });

    if (identity_image === 0) {
        e.preventDefault();
        e.stopImmediatePropagation();
        toastr.error(`{{ translate('messages.please_upload_at_least_one_identity_image') }}`, {
            closeButton: true,
            progressBar: true
        });
        return false;
    }
});

    </script>
@endpush
