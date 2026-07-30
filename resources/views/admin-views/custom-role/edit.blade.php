@extends('layouts.admin.app')
@section('title',translate('Edit Role'))
@push('css_or_js')

@endpush

@section('content')
<div class="content container-fluid">
    <!-- Page Heading -->
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.employee_Role')}}
            </span>
        </h1>
    </div>
    <!-- Page Heading -->
    <!-- Content Row -->
    <div class="row">
        <div class="col-md-12">
            <div class="">
                <div class="">
                    <form action="{{route('admin.users.custom-role.update',[$role['id']])}}" method="post">
                        @csrf
                        <div class="card mb-20">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="title-clr fs-18 mb-1">{{ translate('messages.Role form') }}</h4>
                                    <p class="fs-12 mb-0">{{ translate('messages.Create role and assignee the role module & usage permission.') }}</p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    @if($language)
                                        <ul class="nav nav-tabs mb-4">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active"
                                                href="#"
                                                id="default-link">{{translate('messages.default')}}</a>
                                            </li>
                                            @foreach ($language as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link"
                                                        href="#"
                                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="lang_form" id="default-form">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="default_title">{{translate('messages.role_name')}} ({{translate('messages.default')}}) <span class="form-label-secondary text-danger"
                                                    data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('messages.Required.')}}"> *
                                                    </span>
                                             </label>
                                                <input type="text" name="name[]" id="default_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role?->getRawOriginal('name')}}"  >
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                        @foreach($language as $lang)
                                            <?php
                                                if(count($role['translations'])){
                                                    $translate = [];
                                                    foreach($role['translations'] as $t)
                                                    {
                                                        if($t->locale == $lang && $t->key=="name"){
                                                            $translate[$lang]['name'] = $t->value;
                                                        }
                                                    }
                                                }
                                            ?>
                                            <div class="d-none lang_form" id="{{$lang}}-form">
                                                <div class="form-group mb-0">
                                                    <label class="input-label" for="{{$lang}}_title">{{translate('messages.role_name')}} ({{strtoupper($lang)}})</label>
                                                    <input type="text" name="name[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$translate[$lang]['name']??''}}"  >
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                            </div>
                                        @endforeach
                                    @else
                                    <div id="default-form">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.role_name')}} ({{ translate('messages.default') }})</label>
                                            <input type="text" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{$role['name']}}" maxlength="100">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- <div class="card">
                            <div class="card-header">
                                <div class="d-flex w-100 justify-content-between flex-wrap select--all-checkes gap-2">
                                    <h5 class="input-label m-0 fs-18 title-clr text-capitalize">{{translate('messages.Update_permission')}} : </h5>
                                    <div class="check-item check-item-custom pb-0 w-auto">
                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                            <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input mt-0" id="select-all">
                                            <label class="form-check-label fw-medium pe-inline-end-24 fs-14 title-clr" for="select-all">{{ translate('All Management') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="check--item-wrapper check--item-wrapper-custom">
                                    <div class="shadow-cutom-box-xxl mb-20">
                                        <div class="row g-3">
                                            <div class="col-lg-12">
                                                <div class="">
                                                    <h4 class="title-clr fs-16 mb-20">{{ translate('messages.General') }}</h4>
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Profile Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="general_all">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="general_all">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="dashboard" class="form-check-input"
                                                                        id="dashboard">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="dashboard">{{translate('messages.Dashboard')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="profile" class="form-check-input"
                                                                        id="profile">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="profile">{{translate('messages.Profile')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shadow-cutom-box-xxl mb-20">
                                        <h4 class="title-clr fs-16 mb-20">{{ translate('messages.User Management') }}</h4>
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Promotion Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="profie_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="profie_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="cashback" class="form-check-input"
                                                                        id="cashback">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cashback">{{translate('messages.cashback')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Delivery Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="user_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="user_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="category" class="form-check-input"
                                                                        id="category">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="category">{{translate('messages.Vehicle Category')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="deliveryman" class="form-check-input"
                                                                        id="deliveryman">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman">{{translate('messages.Deliveryman Manage')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Customer Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="customers_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="customers_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="customer_management" class="form-check-input"
                                                                        id="customer_management">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="customer_management">{{translate('messages.customer_management')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="customer_wallet" class="form-check-input"
                                                                        id="customer_wallet">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="customer_wallet">{{translate('messages.Customer Wallet')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="customer_loyalty_point" class="form-check-input"
                                                                        id="customer_loyalty_point">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="customer_loyalty_point">{{translate('messages.Customer Loyalty Point')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="subscription" class="form-check-input"
                                                                        id="subscription">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="subscription">{{translate('messages.subscription')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="contact_messages" class="form-check-input"
                                                                        id="contact_messages">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="contact_messages">{{translate('messages.Contact Messages')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-12">
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Employee Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="employees_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="employees_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="employee_role" class="form-check-input"
                                                                        id="employee_role">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="employee_role">{{translate('messages.Employee Role')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="employee" class="form-check-input"
                                                                        id="employee">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="employee">{{translate('messages.Employee')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shadow-cutom-box-xxl mb-20">
                                        <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Transaction & Report') }}</h4>
                                        <div class="row g-3">
                                            <div class="col-lg-12">
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Business Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="business_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="business_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="withdraw_list" class="form-check-input"
                                                                            id="withdraw_list">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="withdraw_list">{{translate('messages.withdraw_list')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="disbursement" class="form-check-input"
                                                                        id="disbursement">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement">{{translate('messages.disbursement')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input"
                                                                        id="collect_cash">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="collect_cash">{{translate('messages.collect_Cash')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="deliveryman_payments" class="form-check-input"
                                                                        id="deliveryman_payments">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman_payments">{{translate('messages.deliveryman_payments')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Delivery Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="dms_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="dms_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="vehicle_category" class="form-check-input"
                                                                        id="vehicle_category">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle_category">{{translate('messages.vehicle_category')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="deliveryman_manage" class="form-check-input"
                                                                            id="deliveryman_manage">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman_manage">{{translate('messages.deliveryman_manage')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Report & Analytics')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="report_analytics">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="report_analytics">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="report" class="form-check-input"
                                                                            id="report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.Transaction Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="item" class="form-check-input"
                                                                            id="item">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="item">{{translate('messages.Item Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="store" class="form-check-input"
                                                                        id="store">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="store">{{translate('messages.Store Wise Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="expense_report" class="form-check-input"
                                                                        id="expense_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="expense_report">{{translate('messages.expense_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="disbursement_report" class="form-check-input"
                                                                            id="disbursement_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement_report">{{translate('messages.disbursement_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="order" class="form-check-input"
                                                                        id="order">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="order">{{translate('messages.Order Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="admin_text_module" class="form-check-input"
                                                                        id="admin_text_module_system">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="admin_text_module_system">{{translate('messages.Admin Tax Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="vendor_vat_report" class="form-check-input"
                                                                        id="vendor_vat_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vendor_vat_report">{{translate('messages.vendor vat report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item">
                                                                <div class="form-group form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="parcel" class="form-check-input"
                                                                        id="parcel">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="parcel">{{translate('messages.Parcel Tax Report')}}</label>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Rental Report and Analytics')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="rental_report_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="rental_report_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="transaction_report" class="form-check-input"
                                                                        id="transaction_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="transaction_report">{{translate('messages.Transaction Report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="vehicle_reports" class="form-check-input"
                                                                            id="vehicle_reports">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle_reports">{{translate('messages.vehicle_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="provider_wise_report" class="form-check-input"
                                                                            id="provider_wise_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="provider_wise_report">{{translate('messages.provider_wise_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="trip_reports" class="form-check-input"
                                                                            id="trip_reports">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip_reports">{{translate('messages.trip_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="trip_tax_report" class="form-check-input"
                                                                            id="trip_tax_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip_tax_report">{{translate('messages.trip_tax_report')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="provider_vat_reports" class="form-check-input"
                                                                            id="provider_vat_reports">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="provider_vat_reports">{{translate('messages.provider_vat_report')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shadow-cutom-box-xxl mb-20">
                                        <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Settings') }}</h4>
                                        <div class="row g-3">
                                            <div class="col-lg-12">
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Business Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="bsns_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="bsns_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="module" class="form-check-input"
                                                                        id="module_system">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="module_system">{{translate('messages.Module Setup')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="zone" class="form-check-input"
                                                                        id="zone">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="zone">{{translate('messages.zone')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="settings" class="form-check-input"
                                                                        id="settings">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="settings">{{translate('messages.Business Settings')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="system_tax" class="form-check-input"
                                                                        id="system_tax">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="system_tax">{{translate('messages.system_tax')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="subscription_management" class="form-check-input"
                                                                        id="subscription_management">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="subscription_management">{{translate('messages.subscription_management')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="page_social_management" class="form-check-input"
                                                                        id="page_social_management">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="page_social_management">{{translate('messages.Pages & Social Media')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="gallery" class="form-check-input"
                                                                        id="gallery">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="gallery">{{translate('messages.gallery')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.System Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="sys_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="sys_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="third_party-ms" class="form-check-input"
                                                                        id="third_party-ms">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="third_party-ms">{{translate('messages.3rd Party & Configuration')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="login_setup" class="form-check-input"
                                                                        id="login_setup">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="login_setup">{{translate('messages.login_setup')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="email_setups" class="form-check-input"
                                                                        id="email_setups">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="email_setups">{{translate('messages.Email Setup')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="apps_setting" class="form-check-input"
                                                                        id="apps_setting">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="apps_setting">{{translate('messages.App Settings')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item p-0 m-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="addon" class="form-check-input"
                                                                        id="addon">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="addon">{{translate('messages.Addon Activation')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item p-0 m-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="notification" class="form-check-input"
                                                                        id="notification">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="notification">{{translate('messages.Notification Setup')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="clean_database" class="form-check-input"
                                                                        id="clean_database">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="clean_database">{{translate('messages.Clean Database')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 p-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="systems_addons" class="form-check-input"
                                                                        id="systems_addons">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="systems_addons">{{translate('messages.System Addons')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shadow-cutom-box-xxl mb-20">
                                        <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Modules Wise Management') }}</h4>
                                        <div class="row g-3">
                                            <div class="col-lg-12">
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Business Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="module_wises_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="module_wises_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="all_dispatch" class="form-check-input"
                                                                        id="all_dispatch">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="all_dispatch ">{{translate('messages.All Dispatch ')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Order Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="all_orders_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="all_orders_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="order_ms" class="form-check-input"
                                                                        id="order_ms">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="order_ms">{{translate('messages.Orders')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="pos" class="form-check-input"
                                                                        id="pos">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="pos">{{translate('messages.POS Orders')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="mng" class="form-check-input"
                                                                        id="mng">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="dms-mng">{{translate('messages.Delivery Management ')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Promotion Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="promotions_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="promotions_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="campaign" class="form-check-input"
                                                                        id="campaign">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="campaign">{{translate('messages.campaign')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="banner" class="form-check-input"
                                                                        id="banner">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="banner">{{translate('messages.banner')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="coupon" class="form-check-input"
                                                                        id="coupon">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="coupon">{{translate('messages.coupon')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="push_ntf" class="form-check-input"
                                                                        id="push_ntf">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="push_ntf">{{translate('messages.Push Notification ')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item">
                                                                <div class="form-group form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="advertisement" class="form-check-input"
                                                                        id="advertisement">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="advertisement">{{translate('messages.advertisement')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Product Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="products_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="products_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="p_categories" class="form-check-input"
                                                                        id="p_categories">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="p_categories ">{{translate('messages.Categories ')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="attribute" class="form-check-input"
                                                                        id="attribute">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="attribute">{{translate('messages.attribute')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="unit" class="form-check-input"
                                                                        id="unit">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="unit">{{translate('messages.unit')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="common_condition" class="form-check-input"
                                                                        id="common_condition">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="common_condition">{{translate('messages.common_condition')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="brand" class="form-check-input"
                                                                        id="brand">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="brand">{{translate('messages.brand')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="p_addons" class="form-check-input"
                                                                        id="p_addons">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="p_addons">{{translate('messages.Addons')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="p_setups" class="form-check-input"
                                                                        id="p_setups">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="p_setups">{{translate('messages.Product Setup ')}}</label>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-20">
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper h-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Store Management')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="str_management">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="str_management">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-2">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="store_setups" class="form-check-input"
                                                                        id="store_setups">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="store_setups">{{translate('messages.Store Setup')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="recommended_store" class="form-check-input"
                                                                        id="recommended_store">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="recommended_store">{{translate('messages.Recommended Store')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="impor_bulk" class="form-check-input"
                                                                        id="impor_bulk">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="impor_bulk">{{translate('messages.Bulk Import/Export')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('Rental'))
                                    <div class="shadow-cutom-box-xxl mb-0">
                                        <div class="check--item-wrapper m-0 p-0 d-inline-block w-100">
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="">
                                                        <h4 class="title-clr fs-16 mb-20">{{ translate('messages.Rental Management') }}</h4>
                                                    </div>
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper w-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Rental')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="manage__all_rental">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="manage__all_rental">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="trip" class="form-check-input"
                                                                           id="trip">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip">{{translate('messages.Trip')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="promotion" class="form-check-input"
                                                                           id="promotion">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="promotion">{{translate('messages.Promotion')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input"
                                                                           id="vehicle">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle">{{translate('messages.Vehicle')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="provider" class="form-check-input"
                                                                           id="provider">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="provider">{{translate('messages.Provider')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="driver" class="form-check-input"
                                                                           id="driver">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="driver">{{translate('messages.Driver')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="download_app" class="form-check-input"
                                                                           id="download_app">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="download_app">{{translate('messages.Download app')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="rental_report" class="form-check-input"
                                                                           id="rental_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="rental_report">{{translate('messages.Report')}}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div> --}}

                         <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap select--all-checkes">
                                    <h5 class="input-label m-0 text-capitalize">{{translate('messages.Update_permission')}} : </h5>
                                    <div class="check-item pb-0 w-auto">
                                        <div class="form-group form-check form--check m-0 ml-2">
                                            <input type="checkbox" name="modules[]" value="account" class="form-check-input" id="select-all">
                                            <label class="form-check-label ml-2" for="select-all">{{ translate('Select All') }}</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="check--item-wrapper">
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input"
                                                   id="collect_cash"  {{in_array('collect_cash',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="collect_cash">{{translate('messages.collect_cash')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="addon" class="form-check-input"
                                                   id="addon"  {{in_array('addon',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="addon">{{translate('messages.addon')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="attribute" class="form-check-input"
                                                   id="attribute"  {{in_array('attribute',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="attribute">{{translate('messages.attribute')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="advertisement" class="form-check-input"
                                                   id="advertisement"  {{in_array('advertisement',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="advertisement">{{translate('messages.advertisement')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="banner" class="form-check-input"
                                                   id="banner"  {{in_array('banner',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="banner">{{translate('messages.banner')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="campaign" class="form-check-input"
                                                   id="campaign"  {{in_array('campaign',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="campaign">{{translate('messages.campaign')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="category" class="form-check-input"
                                                   id="category"  {{in_array('category',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="category">{{translate('messages.category')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="coupon" class="form-check-input"
                                                   id="coupon"  {{in_array('coupon',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="coupon">{{translate('messages.coupon')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="cashback" class="form-check-input"
                                                   id="cashback"  {{in_array('cashback',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="cashback">{{translate('messages.cashback')}}</label>
                                        </div>
                                    </div>

                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="customer_management" class="form-check-input"
                                                   id="customer_management"  {{in_array('customer_management',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="customer_management">{{translate('messages.customer_management')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="deliveryman" class="form-check-input"
                                                   id="deliveryman"  {{in_array('deliveryman',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="deliveryman">{{translate('messages.deliveryman')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="disbursement" class="form-check-input"
                                                   id="disbursement"  {{in_array('disbursement',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="disbursement">{{translate('messages.disbursement')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="provide_dm_earning" class="form-check-input"
                                                   id="provide_dm_earning"  {{in_array('provide_dm_earning',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="provide_dm_earning">{{translate('messages.provide_dm_earning')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="employee" class="form-check-input"
                                                   id="employee"  {{in_array('employee',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="employee">{{translate('messages.Employee')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="item" class="form-check-input"
                                                   id="item"  {{in_array('item',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="item">{{translate('messages.item')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="notification" class="form-check-input"
                                                   id="notification"  {{in_array('notification',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="notification">{{translate('messages.push_notification')}} </label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="order" class="form-check-input"
                                                   id="order"  {{in_array('order',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="order">{{translate('messages.order')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="store" class="form-check-input"
                                                   id="store"  {{in_array('store',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="store">{{translate('messages.store')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="report" class="form-check-input"
                                                    id="report"  {{in_array('report',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="report">{{translate('messages.report')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="settings" class="form-check-input"
                                                   id="settings"  {{in_array('settings',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="settings">{{translate('messages.settings')}}</label>
                                        </div>
                                    </div>

                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="withdraw_list" class="form-check-input"
                                                    id="withdraw_list"  {{in_array('withdraw_list',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="withdraw_list">{{translate('messages.withdraw_list')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="zone" class="form-check-input"
                                                   id="zone"  {{in_array('zone',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="zone">{{translate('messages.zone')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="module" class="form-check-input"
                                                   id="module_system"  {{in_array('module',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="module_system">{{translate('messages.module')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="parcel" class="form-check-input"
                                                   id="parcel"  {{in_array('parcel',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="parcel">{{translate('messages.parcel')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="pos" class="form-check-input"
                                                   id="pos"  {{in_array('pos',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="pos">{{translate('messages.pos')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="unit" class="form-check-input"
                                                   id="unit"  {{in_array('unit',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="unit">{{translate('messages.unit')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="subscription" class="form-check-input"
                                                   id="subscription"  {{in_array('subscription',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="subscription">{{translate('messages.subscription')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="brand" class="form-check-input"
                                                   id="brand"  {{in_array('brand',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="brand">{{translate('messages.brand')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="common_condition" class="form-check-input"
                                                   id="common_condition"  {{in_array('common_condition',(array)json_decode($role['modules']))?'checked':''}}>
                                            <label class="form-check-label qcont text-dark" for="common_condition">{{translate('messages.common_condition')}}</label>
                                        </div>
                                    </div>

                                @if ( addon_published_status('ReelsModule') )
                                  <div class="check-item">
                                      <div class="form-group form-check form--check">
                                          <input type="checkbox" name="modules[]" value="reels" class="form-check-input"
                                                 id="reels" {{in_array('reels',(array)json_decode($role['modules']))?'checked':''}}>
                                          <label class="form-check-label qcont text-dark" for="reels">{{translate('messages.reels')}}</label>
                                      </div>
                                  </div>
                                @endif
                                </div>
                                <div class="pt-5">
                                    <h4>{{ translate('Urban Goodz Platform') }}</h4>
                                </div>
                                <div class="shadow-cutom-box-xxl mb-20 p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-12">
                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Urban Goodz Command Center') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_command_center_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_command_center_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_view" class="form-check-input" id="urban_goodz_view_edit" {{in_array('urban_goodz_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_view_edit">{{ translate('View Urban Goodz') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_dashboard" class="form-check-input" id="urban_goodz_dashboard_edit" {{in_array('urban_goodz_dashboard',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_dashboard_edit">{{ translate('Command Center Dashboard') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_files" class="form-check-input" id="urban_goodz_files_edit" {{in_array('urban_goodz_files',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_files_edit">{{ translate('File Library') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_control_center" class="form-check-input" id="urban_goodz_control_center_edit" {{in_array('urban_goodz_control_center',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_control_center_edit">{{ translate('Control Center') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_book_anything" class="form-check-input" id="urban_goodz_book_anything_edit" {{in_array('urban_goodz_book_anything',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_book_anything_edit">{{ translate('Book Anything') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_plus" class="form-check-input" id="urban_goodz_plus_edit" {{in_array('urban_goodz_plus',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_plus_edit">{{ translate('Urban Goodz+') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_spotlight" class="form-check-input" id="urban_goodz_spotlight_edit" {{in_array('urban_goodz_spotlight',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_spotlight_edit">{{ translate('Black-Owned Spotlight') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Order Anywhere') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_order_anywhere_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_order_anywhere_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_view" class="form-check-input" id="ug_oa_view_edit" {{in_array('urban_goodz_order_anywhere_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_view_edit">{{ translate('View Orders') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_detail" class="form-check-input" id="ug_oa_detail_edit" {{in_array('urban_goodz_order_anywhere_detail',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_detail_edit">{{ translate('View Order Detail') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_update_status" class="form-check-input" id="ug_oa_status_edit" {{in_array('urban_goodz_order_anywhere_update_status',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_status_edit">{{ translate('Update Status') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_assign_driver" class="form-check-input" id="ug_oa_assign_edit" {{in_array('urban_goodz_order_anywhere_assign_driver',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_assign_edit">{{ translate('Assign Driver') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_quote" class="form-check-input" id="ug_oa_quote_edit" {{in_array('urban_goodz_order_anywhere_quote',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_quote_edit">{{ translate('Create Quote') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_capture_payment" class="form-check-input" id="ug_oa_capture_edit" {{in_array('urban_goodz_order_anywhere_capture_payment',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_capture_edit">{{ translate('Capture Payment') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_refund" class="form-check-input" id="ug_oa_refund_edit" {{in_array('urban_goodz_order_anywhere_refund',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_refund_edit">{{ translate('Issue Refund') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_notes" class="form-check-input" id="ug_oa_notes_edit" {{in_array('urban_goodz_order_anywhere_notes',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_notes_edit">{{ translate('Admin Notes') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Payments / Finance') }} <span class="text-danger">*</span></h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_payments_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_payments_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payments_view" class="form-check-input" id="ug_pay_view_edit" {{in_array('urban_goodz_payments_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_view_edit">{{ translate('View Payment Center') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payment_ledgers_view" class="form-check-input" id="ug_pay_ledgers_edit" {{in_array('urban_goodz_payment_ledgers_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_ledgers_edit">{{ translate('View Ledgers') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payment_splits_view" class="form-check-input" id="ug_pay_splits_edit" {{in_array('urban_goodz_payment_splits_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_splits_edit">{{ translate('View Payment Splits') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_refunds_manage" class="form-check-input" id="ug_refunds_edit" {{in_array('urban_goodz_refunds_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_refunds_edit">{{ translate('Manage Refunds') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_financial_settings" class="form-check-input" id="ug_fin_settings_edit" {{in_array('urban_goodz_financial_settings',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_fin_settings_edit">{{ translate('Financial Settings') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_financial_control_view" class="form-check-input" id="ug_fin_control_view_edit" {{in_array('urban_goodz_financial_control_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_fin_control_view_edit">{{ translate('View Financial Control Center') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_financial_control_manage" class="form-check-input" id="ug_fin_control_manage_edit" {{in_array('urban_goodz_financial_control_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_fin_control_manage_edit">{{ translate('Manage Financial Control Center') }} <span class="text-danger">*</span></label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Rentals') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_rentals_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_rentals_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rentals_view" class="form-check-input" id="ug_rent_view_edit" {{in_array('urban_goodz_rentals_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_view_edit">{{ translate('View Rentals Dashboard') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_assets_view" class="form-check-input" id="ug_rent_assets_view_edit" {{in_array('urban_goodz_rental_assets_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_assets_view_edit">{{ translate('View Assets') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_assets_manage" class="form-check-input" id="ug_rent_assets_mgmt_edit" {{in_array('urban_goodz_rental_assets_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_assets_mgmt_edit">{{ translate('Manage Assets') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_bookings_view" class="form-check-input" id="ug_rent_book_view_edit" {{in_array('urban_goodz_rental_bookings_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_book_view_edit">{{ translate('View Bookings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_bookings_manage" class="form-check-input" id="ug_rent_book_mgmt_edit" {{in_array('urban_goodz_rental_bookings_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_book_mgmt_edit">{{ translate('Manage Bookings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_deposits_manage" class="form-check-input" id="ug_rent_deposits_edit" {{in_array('urban_goodz_rental_deposits_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_deposits_edit">{{ translate('Manage Deposits') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_verification_manage" class="form-check-input" id="ug_rent_verify_edit" {{in_array('urban_goodz_rental_verification_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_verify_edit">{{ translate('Manage Verification') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_inspections_manage" class="form-check-input" id="ug_rent_insp_edit" {{in_array('urban_goodz_rental_inspections_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_insp_edit">{{ translate('Manage Inspections') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_damage_reports_manage" class="form-check-input" id="ug_rent_damage_edit" {{in_array('urban_goodz_rental_damage_reports_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_damage_edit">{{ translate('Manage Damage Reports') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Vehicle Rentals') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_veh_rentals_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_veh_rentals_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rentals_view" class="form-check-input" id="ug_vr_view_edit" {{in_array('urban_goodz_vehicle_rentals_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_view_edit">{{ translate('View Vehicle Rentals') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rentals_manage" class="form-check-input" id="ug_vr_mgmt_edit" {{in_array('urban_goodz_vehicle_rentals_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_mgmt_edit">{{ translate('Manage Vehicle Rentals') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_availability_manage" class="form-check-input" id="ug_vr_avail_edit" {{in_array('urban_goodz_vehicle_availability_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_avail_edit">{{ translate('Manage Availability') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rates_manage" class="form-check-input" id="ug_vr_rates_edit" {{in_array('urban_goodz_vehicle_rates_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_rates_edit">{{ translate('Manage Rates') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_pickup_return_manage" class="form-check-input" id="ug_vr_pickup_edit" {{in_array('urban_goodz_vehicle_pickup_return_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_pickup_edit">{{ translate('Manage Pickup/Return') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_damage_reports_manage" class="form-check-input" id="ug_vr_damage_edit" {{in_array('urban_goodz_vehicle_damage_reports_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_damage_edit">{{ translate('Manage Damage Reports') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Fashion Fit') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_fashion_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_fashion_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_fit_view" class="form-check-input" id="ug_ff_view_edit" {{in_array('urban_goodz_fashion_fit_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_view_edit">{{ translate('View Fashion Fit') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_measurements_view" class="form-check-input" id="ug_ff_meas_view_edit" {{in_array('urban_goodz_fashion_measurements_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_meas_view_edit">{{ translate('View Measurements') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_measurements_manage" class="form-check-input" id="ug_ff_meas_mgmt_edit" {{in_array('urban_goodz_fashion_measurements_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_meas_mgmt_edit">{{ translate('Manage Measurements') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_stylist_requests_view" class="form-check-input" id="ug_ff_stylist_view_edit" {{in_array('urban_goodz_stylist_requests_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_stylist_view_edit">{{ translate('View Stylist Requests') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_stylist_requests_manage" class="form-check-input" id="ug_ff_stylist_mgmt_edit" {{in_array('urban_goodz_stylist_requests_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_stylist_mgmt_edit">{{ translate('Manage Stylist Requests') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_files_manage" class="form-check-input" id="ug_ff_files_edit" {{in_array('urban_goodz_fashion_files_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_files_edit">{{ translate('Manage Fashion Files') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('AI Concierge') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_ai_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_ai_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_concierge_view" class="form-check-input" id="ug_ai_view_edit" {{in_array('urban_goodz_ai_concierge_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_view_edit">{{ translate('View AI Concierge') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_conversations_view" class="form-check-input" id="ug_ai_conv_view_edit" {{in_array('urban_goodz_ai_conversations_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_conv_view_edit">{{ translate('View Conversations') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_conversations_manage" class="form-check-input" id="ug_ai_conv_mgmt_edit" {{in_array('urban_goodz_ai_conversations_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_conv_mgmt_edit">{{ translate('Manage Conversations') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_intents_view" class="form-check-input" id="ug_ai_intents_view_edit" {{in_array('urban_goodz_ai_intents_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_intents_view_edit">{{ translate('View Intents') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_intents_manage" class="form-check-input" id="ug_ai_intents_mgmt_edit" {{in_array('urban_goodz_ai_intents_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_intents_mgmt_edit">{{ translate('Manage Intents') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_settings_view" class="form-check-input" id="ug_ai_settings_view_edit" {{in_array('urban_goodz_ai_settings_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_settings_view_edit">{{ translate('View AI Settings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_settings_manage" class="form-check-input" id="ug_ai_settings_mgmt_edit" {{in_array('urban_goodz_ai_settings_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_settings_mgmt_edit">{{ translate('Manage AI Settings') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_usage_view" class="form-check-input" id="ug_ai_usage_edit" {{in_array('urban_goodz_ai_usage_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_usage_edit">{{ translate('View AI Usage') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_copilot_use" class="form-check-input" id="ug_ai_copilot_edit" {{in_array('urban_goodz_ai_copilot_use',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_copilot_edit">{{ translate('Use AI Copilot') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Business Discovery / Marketplace') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_discovery_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_discovery_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_discovery_view" class="form-check-input" id="ug_bd_view_edit" {{in_array('urban_goodz_business_discovery_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_view_edit">{{ translate('View Business Discovery') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_leads_view" class="form-check-input" id="ug_bd_leads_view_edit" {{in_array('urban_goodz_business_leads_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_leads_view_edit">{{ translate('View Business Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_leads_manage" class="form-check-input" id="ug_bd_leads_mgmt_edit" {{in_array('urban_goodz_business_leads_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_leads_mgmt_edit">{{ translate('Manage Business Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_types_view" class="form-check-input" id="ug_bd_types_view_edit" {{in_array('urban_goodz_business_types_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_types_view_edit">{{ translate('View Business Types') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_types_manage" class="form-check-input" id="ug_bd_types_mgmt_edit" {{in_array('urban_goodz_business_types_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_types_mgmt_edit">{{ translate('Manage Business Types') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_capabilities_view" class="form-check-input" id="ug_bd_caps_view_edit" {{in_array('urban_goodz_capabilities_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_caps_view_edit">{{ translate('View Capabilities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_capabilities_manage" class="form-check-input" id="ug_bd_caps_mgmt_edit" {{in_array('urban_goodz_capabilities_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_caps_mgmt_edit">{{ translate('Manage Capabilities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_module_mapping_manage" class="form-check-input" id="ug_bd_mapping_edit" {{in_array('urban_goodz_module_mapping_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_mapping_edit">{{ translate('Manage Module Mapping') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Earn Money / Partners') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_earn_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_earn_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_earn_money_view" class="form-check-input" id="ug_em_view_edit" {{in_array('urban_goodz_earn_money_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_view_edit">{{ translate('View Earn Money') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_driver_opportunities_view" class="form-check-input" id="ug_em_driver_view_edit" {{in_array('urban_goodz_driver_opportunities_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_driver_view_edit">{{ translate('View Driver Opportunities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_driver_opportunities_manage" class="form-check-input" id="ug_em_driver_mgmt_edit" {{in_array('urban_goodz_driver_opportunities_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_driver_mgmt_edit">{{ translate('Manage Driver Opportunities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_partner_applications_view" class="form-check-input" id="ug_em_partner_view_edit" {{in_array('urban_goodz_partner_applications_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_partner_view_edit">{{ translate('View Partner Apps') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_partner_applications_manage" class="form-check-input" id="ug_em_partner_mgmt_edit" {{in_array('urban_goodz_partner_applications_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_partner_mgmt_edit">{{ translate('Manage Partner Apps') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Logistics / Medical Courier') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_logistics_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_logistics_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_view" class="form-check-input" id="ug_log_view_edit" {{in_array('urban_goodz_logistics_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_view_edit">{{ translate('View Logistics') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_jobs_view" class="form-check-input" id="ug_log_jobs_view_edit" {{in_array('urban_goodz_logistics_jobs_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_jobs_view_edit">{{ translate('View Logistics Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_jobs_manage" class="form-check-input" id="ug_log_jobs_mgmt_edit" {{in_array('urban_goodz_logistics_jobs_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_jobs_mgmt_edit">{{ translate('Manage Logistics Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_load_board_view" class="form-check-input" id="ug_log_load_view_edit" {{in_array('urban_goodz_load_board_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_load_view_edit">{{ translate('View Load Board') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_load_board_manage" class="form-check-input" id="ug_log_load_mgmt_edit" {{in_array('urban_goodz_load_board_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_load_mgmt_edit">{{ translate('Manage Load Board') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_view" class="form-check-input" id="ug_mc_view_edit" {{in_array('urban_goodz_medical_courier_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_view_edit">{{ translate('View Medical Courier') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_jobs_view" class="form-check-input" id="ug_mc_jobs_view_edit" {{in_array('urban_goodz_medical_courier_jobs_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_jobs_view_edit">{{ translate('View Courier Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_jobs_manage" class="form-check-input" id="ug_mc_jobs_mgmt_edit" {{in_array('urban_goodz_medical_courier_jobs_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_jobs_mgmt_edit">{{ translate('Manage Courier Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_custody_manage" class="form-check-input" id="ug_mc_custody_edit" {{in_array('urban_goodz_medical_courier_custody_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_custody_edit">{{ translate('Manage Custody Logs') }} <span class="text-danger">*</span></label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Events / Creators / Community') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_events_edit">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_events_edit">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_events_view" class="form-check-input" id="ug_ev_view_edit" {{in_array('urban_goodz_events_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ev_view_edit">{{ translate('View Events') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_events_manage" class="form-check-input" id="ug_ev_mgmt_edit" {{in_array('urban_goodz_events_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ev_mgmt_edit">{{ translate('Manage Events') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_commerce_view" class="form-check-input" id="ug_cc_view_edit" {{in_array('urban_goodz_creator_commerce_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_view_edit">{{ translate('View Creator Commerce') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_applications_view" class="form-check-input" id="ug_cc_apps_view_edit" {{in_array('urban_goodz_creator_applications_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_apps_view_edit">{{ translate('View Applications') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_applications_manage" class="form-check-input" id="ug_cc_apps_edit" {{in_array('urban_goodz_creator_applications_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_apps_edit">{{ translate('Manage Applications') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_profiles_view" class="form-check-input" id="ug_cc_prof_view_edit" {{in_array('urban_goodz_creator_profiles_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_prof_view_edit">{{ translate('View Profiles') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_profiles_manage" class="form-check-input" id="ug_cc_prof_mgmt_edit" {{in_array('urban_goodz_creator_profiles_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_prof_mgmt_edit">{{ translate('Manage Profiles') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_campaigns_view" class="form-check-input" id="ug_cc_camp_view_edit" {{in_array('urban_goodz_creator_campaigns_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_camp_view_edit">{{ translate('View Campaigns') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_campaigns_manage" class="form-check-input" id="ug_cc_camp_mgmt_edit" {{in_array('urban_goodz_creator_campaigns_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_camp_mgmt_edit">{{ translate('Manage Campaigns') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_content_view" class="form-check-input" id="ug_cc_cont_view_edit" {{in_array('urban_goodz_creator_content_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_cont_view_edit">{{ translate('View Content') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_content_manage" class="form-check-input" id="ug_cc_cont_mgmt_edit" {{in_array('urban_goodz_creator_content_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_cont_mgmt_edit">{{ translate('Manage Content') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_earnings_view" class="form-check-input" id="ug_cc_earn_view_edit" {{in_array('urban_goodz_creator_earnings_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_earn_view_edit">{{ translate('View Earnings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_payouts_manage" class="form-check-input" id="ug_cc_payouts_edit" {{in_array('urban_goodz_creator_payouts_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_payouts_edit">{{ translate('Manage Payouts') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_leads_view" class="form-check-input" id="ug_cc_leads_view_edit" {{in_array('urban_goodz_creator_leads_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_leads_view_edit">{{ translate('View Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_leads_manage" class="form-check-input" id="ug_cc_leads_mgmt_edit" {{in_array('urban_goodz_creator_leads_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_leads_mgmt_edit">{{ translate('Manage Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_reports_view" class="form-check-input" id="ug_cc_reports_edit" {{in_array('urban_goodz_creator_reports_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_reports_edit">{{ translate('View Reports') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_ai_tools_use" class="form-check-input" id="ug_cc_ai_edit" {{in_array('urban_goodz_creator_ai_tools_use',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_ai_edit">{{ translate('Use AI Creator Tools') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_community_marketplace_view" class="form-check-input" id="ug_com_view_edit" {{in_array('urban_goodz_community_marketplace_view',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_com_view_edit">{{ translate('View Community Marketplace') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_community_marketplace_manage" class="form-check-input" id="ug_com_mgmt_edit" {{in_array('urban_goodz_community_marketplace_manage',(array)json_decode($role['modules']))?'checked':''}}><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_com_mgmt_edit">{{ translate('Manage Community Marketplace') }}</label></div></div>
                                                </div>
                                            </div>

                                            <p class="text-muted small mt-2"><span class="text-danger">*</span> {{ translate('Sensitive action — requires additional access') }}</p>
                                        </div>
                                    </div>
                                </div>
                                @if (addon_published_status('Rental'))
                                    <div class="pt-5">
                                        <h4>{{translate('Rental Role')}}</h4>
                                    </div>
                                    <div class="check--item-wrapper">
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="trip" class="form-check-input"
                                                       id="trip" {{in_array('trip',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="trip">{{translate('messages.Trip')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="promotion" class="form-check-input"
                                                       id="promotion" {{in_array('promotion',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="promotion">{{translate('messages.Promotion')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input"
                                                       id="vehicle" {{in_array('vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="vehicle">{{translate('messages.Vehicle')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="provider" class="form-check-input"
                                                       id="provider" {{in_array('provider',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="provider">{{translate('messages.Provider')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="driver" class="form-check-input"
                                                       id="driver" {{in_array('driver',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="driver">{{translate('messages.Driver')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="download_app" class="form-check-input"
                                                       id="download_app" {{in_array('download_app',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="download_app">{{translate('messages.Download app')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="rental_report" class="form-check-input"
                                                       id="rental_report" {{in_array('rental_report',(array)json_decode($role['modules']))?'checked':''}}>
                                                <label class="form-check-label qcont text-dark" for="rental_report">{{translate('messages.Report')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                        @if (addon_published_status('RideShare'))
                            <div class="pt-5">
                                <h4>{{translate('Ride Share Role')}}</h4>
                            </div>
                            <div class="check--item-wrapper">
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="heat_map" class="form-check-input"
                                               id="heat_map" {{in_array('heat_map',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="heat_map">{{translate('messages.heat_map')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="fleet_view" class="form-check-input"
                                               id="fleet_view" {{in_array('fleet_view',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="fleet_view">{{translate('messages.fleet_view')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride" class="form-check-input"
                                               id="ride" {{in_array('ride',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="ride">{{translate('messages.ride')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_promotion" class="form-check-input"
                                               id="ride_promotion" {{in_array('ride_promotion',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="ride_promotion">{{translate('messages.promotion')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="fare" class="form-check-input"
                                               id="fare" {{in_array('fare',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="fare">{{translate('messages.fare')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_vehicle" class="form-check-input"
                                               id="ride_vehicle" {{in_array('ride_vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="ride_vehicle">{{translate('messages.vehicle')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="rider" class="form-check-input"
                                               id="rider" {{in_array('rider',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="rider">{{translate('messages.rider')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_report" class="form-check-input"
                                               id="ride_report" {{in_array('ride_report',(array)json_decode($role['modules']))?'checked':''}}>
                                        <label class="form-check-label qcont text-dark" for="ride_report">{{translate('messages.report')}}</label>
                                    </div>
                                </div>
                            </div>
                        @endif
                            </div>
                        </div>

                        <div class="btn--container justify-content-end mt-4">
                            <button type="reset" class="btn btn--reset min-w-120px">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary min-w-120px">{{translate('messages.update')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/custom-role-index.js"></script>
@endpush
