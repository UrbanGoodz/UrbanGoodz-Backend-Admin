@extends('layouts.admin.app')
@section('title',translate('messages.custom_role'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.employee_Role')}}
            </span>
        </h1>
    </div>
    <!-- End Page Header -->
    <!-- Content Row -->
    <div class="row">
        <div class="col-md-12">

            <div class="">
                <div class="">
                    <form action="{{route('admin.users.custom-role.store')}}" method="post">
                        @csrf
                        <div class="card mb-20">
                            <div class="card-body">
                                <div class="mb-20">
                                    <h4 class="title-clr fs-18 mb-1">{{ translate('messages.Role form') }}</h4>
                                    <p class="fs-12 mb-0">{{ translate('messages.Create role and assignee the role module & usage permission.') }}</p>
                                </div>
                                <div class="bg-light2 rounded p-xxl-20 p-3">
                                    @if ($language)
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
                                    <div class="form-group mb-0 lang_form" id="default-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.role_name')}} ({{ translate('messages.default') }}) <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span>
                                        </label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                        @foreach($language as $lang)
                                            <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.role_name')}} ({{strtoupper($lang)}})</label>
                                                <input type="text" name="name[]" class="form-control" placeholder="{{translate('role_name_example')}}" maxlength="191">
                                            </div>
                                            <input type="hidden" name="lang[]" value="{{$lang}}">
                                        @endforeach
                                    @else
                                        <div class="form-group">
                                            <label class="input-label" for="exampleFormControlInput1">{{translate('messages.role_name')}}</label>
                                            <input type="text" name="name" class="form-control" placeholder="{{translate('role_name_example')}}" value="{{old('name')}}" maxlength="191">
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- <div class="card">
                            <div class="card-header">
                                <div class="d-flex w-100 justify-content-between flex-wrap select--all-checkes gap-2">
                                    <h5 class="input-label m-0 fs-18 title-clr text-capitalize">{{translate('messages.Set_permission')}} : </h5>
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
                                                                    <input type="checkbox" name="modules[]" value="vehicle_category" class="form-check-input"
                                                                        id="vehicle_category">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle_category">{{translate('messages.Vehicle Category')}}</label>
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

                                @if (addon_published_status('RideShare'))
                                    <div class="shadow-cutom-box-xxl mb-0">
                                        <div class="check--item-wrapper m-0 p-0 d-inline-block w-100">
                                            <div class="row g-3">
                                                <div class="col-lg-12">
                                                    <div class="">
                                                        <h4 class="title-clr fs-16 mb-20">{{ translate('Ride Share Management') }}</h4>
                                                    </div>
                                                    <div class="bg-light2 rounded sub_slect_all_wrapper w-100">
                                                        <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                            <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{translate('messages.Ride Share')}} </h5>
                                                            <div class="check-item check-item-custom pb-0 w-auto">
                                                                <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                                    <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="manage__all_ride_share">
                                                                    <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="manage__all_ride_share">{{ translate('Select All') }}</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="heat_map" class="form-check-input"
                                                                           id="heat_map">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="heat_map">{{translate('messages.heat_map')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="fleet_view" class="form-check-input"
                                                                           id="fleet_view">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="fleet_view">{{translate('messages.fleet_view')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="ride" class="form-check-input"
                                                                           id="ride">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="ride">{{translate('messages.ride')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="ride_promotion" class="form-check-input"
                                                                           id="ride_promotion">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="ride_promotion">{{translate('messages.ride_promotion')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="fare" class="form-check-input"
                                                                           id="fare">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="fare">{{translate('messages.fare')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="ride_vehicle" class="form-check-input"
                                                                           id="ride_vehicle">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="ride_vehicle">{{translate('messages.vehicle')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="rider" class="form-check-input"
                                                                           id="rider">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="rider">{{translate('messages.rider')}}</label>
                                                                </div>
                                                            </div>
                                                            <div class="check-item m-0 p-0">
                                                                <div class="form-group m-0 form-check form--check">
                                                                    <input type="checkbox" name="modules[]" value="ride_report" class="form-check-input"
                                                                           id="ride_report">
                                                                    <label class="form-check-label ps--3 qcont text-dark opacity-70" for="ride_report">{{translate('messages.report')}}</label>
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
                            <div class="card-header">
                                <div class="d-flex w-100 justify-content-between flex-wrap select--all-checkes gap-2">
                                    <h5 class="input-label m-0 fs-18 title-clr text-capitalize">{{translate('messages.Set_permission')}} : </h5>
                                    <div class="check-item check-item-custom pb-0 w-auto">
                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                            <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input mt-0" id="select-all">
                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="select-all">{{ translate('All Management') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="check--item-wrapper">
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="collect_cash" class="form-check-input"
                                                   id="collect_cash">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="collect_cash">{{translate('messages.collect_Cash')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="addon" class="form-check-input"
                                                   id="addon">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="addon">{{translate('messages.addon')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="attribute" class="form-check-input"
                                                   id="attribute">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="attribute">{{translate('messages.attribute')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="advertisement" class="form-check-input"
                                                   id="advertisement">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="advertisement">{{translate('messages.advertisement')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="banner" class="form-check-input"
                                                   id="banner">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="banner">{{translate('messages.banner')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="campaign" class="form-check-input"
                                                   id="campaign">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="campaign">{{translate('messages.campaign')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="category" class="form-check-input"
                                                   id="category">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="category">{{translate('messages.category')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="coupon" class="form-check-input"
                                                   id="coupon">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="coupon">{{translate('messages.coupon')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="cashback" class="form-check-input"
                                                   id="cashback">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="cashback">{{translate('messages.cashback')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="customer_management" class="form-check-input"
                                                   id="customer_management">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="customer_management">{{translate('messages.customer_management')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="deliveryman" class="form-check-input"
                                                   id="deliveryman">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="deliveryman">{{translate('messages.deliveryman')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="disbursement" class="form-check-input"
                                                   id="disbursement">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="disbursement">{{translate('messages.disbursement')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="provide_dm_earning" class="form-check-input"
                                                   id="provide_dm_earning">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="provide_dm_earning">{{translate('messages.provide_dm_earning')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="employee" class="form-check-input"
                                                   id="employee">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="employee">{{translate('messages.Employee')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="item" class="form-check-input"
                                                   id="item">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="item">{{translate('messages.item')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="notification" class="form-check-input"
                                                   id="notification">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="notification">{{translate('messages.notification')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="order" class="form-check-input"
                                                   id="order">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="order">{{translate('messages.order')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="store" class="form-check-input"
                                                   id="store">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="store">{{translate('messages.store')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="report" class="form-check-input"
                                                    id="report">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="report">{{translate('messages.report')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="settings" class="form-check-input"
                                                   id="settings">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="settings">{{translate('messages.settings')}}</label>
                                        </div>
                                    </div>

                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="withdraw_list" class="form-check-input"
                                                    id="withdraw_list">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="withdraw_list">{{translate('messages.withdraw_list')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="zone" class="form-check-input"
                                                   id="zone">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="zone">{{translate('messages.zone')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="module" class="form-check-input"
                                                   id="module_system">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="module_system">{{translate('messages.module')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="parcel" class="form-check-input"
                                                   id="parcel">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="parcel">{{translate('messages.parcel')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="pos" class="form-check-input"
                                                   id="pos">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="pos">{{translate('messages.pos')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="unit" class="form-check-input"
                                                   id="unit">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="unit">{{translate('messages.unit')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="subscription" class="form-check-input"
                                                   id="subscription">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="subscription">{{translate('messages.subscription')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="brand" class="form-check-input"
                                                   id="brand">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="brand">{{translate('messages.brand')}}</label>
                                        </div>
                                    </div>
                                    <div class="check-item">
                                        <div class="form-group form-check form--check">
                                            <input type="checkbox" name="modules[]" value="common_condition" class="form-check-input"
                                                   id="common_condition">
                                            <label class="form-check-label ps--3 qcont text-dark opacity-70" for="common_condition">{{translate('messages.common_condition')}}</label>
                                        </div>
                                    </div>

                                @if (  addon_published_status('ReelsModule')  )
                                  <div class="check-item">
                                      <div class="form-group form-check form--check">
                                          <input type="checkbox" name="modules[]" value="reels" class="form-check-input"
                                                 id="reels">
                                          <label class="form-check-label ps--3 qcont text-dark opacity-70" for="reels">{{translate('messages.reels')}}</label>
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
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_command_center">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_command_center">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_view" class="form-check-input" id="urban_goodz_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_view">{{ translate('View Urban Goodz') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_dashboard" class="form-check-input" id="urban_goodz_dashboard"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_dashboard">{{ translate('Command Center Dashboard') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_files" class="form-check-input" id="urban_goodz_files"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_files">{{ translate('File Library') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_control_center" class="form-check-input" id="urban_goodz_control_center"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_control_center">{{ translate('Control Center') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_book_anything" class="form-check-input" id="urban_goodz_book_anything"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_book_anything">{{ translate('Book Anything') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_plus" class="form-check-input" id="urban_goodz_plus"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_plus">{{ translate('Urban Goodz+') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_spotlight" class="form-check-input" id="urban_goodz_spotlight"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="urban_goodz_spotlight">{{ translate('Black-Owned Spotlight') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Order Anywhere') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_order_anywhere">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_order_anywhere">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_view" class="form-check-input" id="ug_oa_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_view">{{ translate('View Orders') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_detail" class="form-check-input" id="ug_oa_detail"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_detail">{{ translate('View Order Detail') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_update_status" class="form-check-input" id="ug_oa_status"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_status">{{ translate('Update Status') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_assign_driver" class="form-check-input" id="ug_oa_assign"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_assign">{{ translate('Assign Driver') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_quote" class="form-check-input" id="ug_oa_quote"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_quote">{{ translate('Create Quote') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_capture_payment" class="form-check-input" id="ug_oa_capture"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_capture">{{ translate('Capture Payment') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_refund" class="form-check-input" id="ug_oa_refund"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_refund">{{ translate('Issue Refund') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_request_card" class="form-check-input" id="ug_oa_request_card"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_request_card">{{ translate('Request Purchase Card') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_freeze_card" class="form-check-input" id="ug_oa_freeze_card"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_freeze_card">{{ translate('Freeze Purchase Card') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_cancel_card" class="form-check-input" id="ug_oa_cancel_card"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_cancel_card">{{ translate('Cancel Purchase Card') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_reconcile_card" class="form-check-input" id="ug_oa_reconcile_card"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_reconcile_card">{{ translate('Reconcile Purchase Card') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_order_anywhere_notes" class="form-check-input" id="ug_oa_notes"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_oa_notes">{{ translate('Admin Notes') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Payments / Finance') }} <span class="text-danger">*</span></h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_payments">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_payments">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payments_view" class="form-check-input" id="ug_pay_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_view">{{ translate('View Payment Center') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payment_ledgers_view" class="form-check-input" id="ug_pay_ledgers"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_ledgers">{{ translate('View Ledgers') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_payment_splits_view" class="form-check-input" id="ug_pay_splits"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_pay_splits">{{ translate('View Payment Splits') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_refunds_manage" class="form-check-input" id="ug_refunds"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_refunds">{{ translate('Manage Refunds') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_financial_settings" class="form-check-input" id="ug_fin_settings"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_fin_settings">{{ translate('Financial Settings') }} <span class="text-danger">*</span></label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Rentals') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_rentals">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_rentals">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rentals_view" class="form-check-input" id="ug_rent_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_view">{{ translate('View Rentals Dashboard') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_assets_view" class="form-check-input" id="ug_rent_assets_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_assets_view">{{ translate('View Assets') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_assets_manage" class="form-check-input" id="ug_rent_assets_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_assets_mgmt">{{ translate('Manage Assets') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_bookings_view" class="form-check-input" id="ug_rent_book_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_book_view">{{ translate('View Bookings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_bookings_manage" class="form-check-input" id="ug_rent_book_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_book_mgmt">{{ translate('Manage Bookings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_deposits_manage" class="form-check-input" id="ug_rent_deposits"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_deposits">{{ translate('Manage Deposits') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_verification_manage" class="form-check-input" id="ug_rent_verify"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_verify">{{ translate('Manage Verification') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_inspections_manage" class="form-check-input" id="ug_rent_insp"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_insp">{{ translate('Manage Inspections') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_rental_damage_reports_manage" class="form-check-input" id="ug_rent_damage"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_rent_damage">{{ translate('Manage Damage Reports') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Vehicle Rentals') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_veh_rentals">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_veh_rentals">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rentals_view" class="form-check-input" id="ug_vr_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_view">{{ translate('View Vehicle Rentals') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rentals_manage" class="form-check-input" id="ug_vr_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_mgmt">{{ translate('Manage Vehicle Rentals') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_availability_manage" class="form-check-input" id="ug_vr_avail"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_avail">{{ translate('Manage Availability') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_rates_manage" class="form-check-input" id="ug_vr_rates"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_rates">{{ translate('Manage Rates') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_pickup_return_manage" class="form-check-input" id="ug_vr_pickup"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_pickup">{{ translate('Manage Pickup/Return') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_vehicle_damage_reports_manage" class="form-check-input" id="ug_vr_damage"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_vr_damage">{{ translate('Manage Damage Reports') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Fashion Fit') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_fashion">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_fashion">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_fit_view" class="form-check-input" id="ug_ff_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_view">{{ translate('View Fashion Fit') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_measurements_view" class="form-check-input" id="ug_ff_meas_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_meas_view">{{ translate('View Measurements') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_measurements_manage" class="form-check-input" id="ug_ff_meas_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_meas_mgmt">{{ translate('Manage Measurements') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_stylist_requests_view" class="form-check-input" id="ug_ff_stylist_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_stylist_view">{{ translate('View Stylist Requests') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_stylist_requests_manage" class="form-check-input" id="ug_ff_stylist_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_stylist_mgmt">{{ translate('Manage Stylist Requests') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_fashion_files_manage" class="form-check-input" id="ug_ff_files"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ff_files">{{ translate('Manage Fashion Files') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('AI Concierge') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_ai">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_ai">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_concierge_view" class="form-check-input" id="ug_ai_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_view">{{ translate('View AI Concierge') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_conversations_view" class="form-check-input" id="ug_ai_conv_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_conv_view">{{ translate('View Conversations') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_conversations_manage" class="form-check-input" id="ug_ai_conv_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_conv_mgmt">{{ translate('Manage Conversations') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_intents_view" class="form-check-input" id="ug_ai_intents_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_intents_view">{{ translate('View Intents') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_intents_manage" class="form-check-input" id="ug_ai_intents_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_intents_mgmt">{{ translate('Manage Intents') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_settings_view" class="form-check-input" id="ug_ai_settings_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_settings_view">{{ translate('View AI Settings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_settings_manage" class="form-check-input" id="ug_ai_settings_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_settings_mgmt">{{ translate('Manage AI Settings') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_usage_view" class="form-check-input" id="ug_ai_usage"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_usage">{{ translate('View AI Usage') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_ai_copilot_use" class="form-check-input" id="ug_ai_copilot"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ai_copilot">{{ translate('Use AI Copilot') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Business Discovery / Marketplace') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_discovery">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_discovery">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_discovery_view" class="form-check-input" id="ug_bd_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_view">{{ translate('View Business Discovery') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_leads_view" class="form-check-input" id="ug_bd_leads_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_leads_view">{{ translate('View Business Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_leads_manage" class="form-check-input" id="ug_bd_leads_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_leads_mgmt">{{ translate('Manage Business Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_types_view" class="form-check-input" id="ug_bd_types_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_types_view">{{ translate('View Business Types') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_business_types_manage" class="form-check-input" id="ug_bd_types_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_types_mgmt">{{ translate('Manage Business Types') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_capabilities_view" class="form-check-input" id="ug_bd_caps_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_caps_view">{{ translate('View Capabilities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_capabilities_manage" class="form-check-input" id="ug_bd_caps_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_caps_mgmt">{{ translate('Manage Capabilities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_module_mapping_manage" class="form-check-input" id="ug_bd_mapping"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_bd_mapping">{{ translate('Manage Module Mapping') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Earn Money / Partners') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_earn">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_earn">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_earn_money_view" class="form-check-input" id="ug_em_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_view">{{ translate('View Earn Money') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_driver_opportunities_view" class="form-check-input" id="ug_em_driver_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_driver_view">{{ translate('View Driver Opportunities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_driver_opportunities_manage" class="form-check-input" id="ug_em_driver_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_driver_mgmt">{{ translate('Manage Driver Opportunities') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_partner_applications_view" class="form-check-input" id="ug_em_partner_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_partner_view">{{ translate('View Partner Apps') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_partner_applications_manage" class="form-check-input" id="ug_em_partner_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_em_partner_mgmt">{{ translate('Manage Partner Apps') }}</label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Logistics / Medical Courier') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_logistics">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_logistics">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_view" class="form-check-input" id="ug_log_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_view">{{ translate('View Logistics') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_jobs_view" class="form-check-input" id="ug_log_jobs_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_jobs_view">{{ translate('View Logistics Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_logistics_jobs_manage" class="form-check-input" id="ug_log_jobs_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_jobs_mgmt">{{ translate('Manage Logistics Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_load_board_view" class="form-check-input" id="ug_log_load_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_load_view">{{ translate('View Load Board') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_load_board_manage" class="form-check-input" id="ug_log_load_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_log_load_mgmt">{{ translate('Manage Load Board') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_view" class="form-check-input" id="ug_mc_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_view">{{ translate('View Medical Courier') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_jobs_view" class="form-check-input" id="ug_mc_jobs_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_jobs_view">{{ translate('View Courier Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_jobs_manage" class="form-check-input" id="ug_mc_jobs_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_jobs_mgmt">{{ translate('Manage Courier Jobs') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_medical_courier_custody_manage" class="form-check-input" id="ug_mc_custody"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_mc_custody">{{ translate('Manage Custody Logs') }} <span class="text-danger">*</span></label></div></div>
                                                </div>
                                            </div>

                                            <div class="bg-light2 rounded sub_slect_all_wrapper h-100 mb-20">
                                                <div class="d-flex px-xxl-20 px-3 p-12 w-100 justify-content-between flex-wrap gap-2 border-bottom">
                                                    <h5 class="input-label m-0 fs-14 title-clr text-capitalize">{{ translate('Events / Creators / Community') }}</h5>
                                                    <div class="check-item check-item-custom pb-0 w-auto">
                                                        <div class="form-group flex-row-reverse d-flex align-items-center form-check form--check pe-inline-start0 pe-inline-end0 m-0">
                                                            <input type="checkbox" name="modules[]" value="" class="form-check-input mt-0 sub_select-all" id="ug_events">
                                                            <label class="form-check-label pe-inline-end-24 fs-14 title-clr" for="ug_events">{{ translate('Select All') }}</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-xxl-20 p-3 d-flex flex-wrap gap-3">
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_events_view" class="form-check-input" id="ug_ev_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ev_view">{{ translate('View Events') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_events_manage" class="form-check-input" id="ug_ev_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_ev_mgmt">{{ translate('Manage Events') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_commerce_view" class="form-check-input" id="ug_cc_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_view">{{ translate('View Creator Commerce') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_applications_view" class="form-check-input" id="ug_cc_apps_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_apps_view">{{ translate('View Applications') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_applications_manage" class="form-check-input" id="ug_cc_apps"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_apps">{{ translate('Manage Applications') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_profiles_view" class="form-check-input" id="ug_cc_prof_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_prof_view">{{ translate('View Profiles') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_profiles_manage" class="form-check-input" id="ug_cc_prof_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_prof_mgmt">{{ translate('Manage Profiles') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_campaigns_view" class="form-check-input" id="ug_cc_camp_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_camp_view">{{ translate('View Campaigns') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_campaigns_manage" class="form-check-input" id="ug_cc_camp_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_camp_mgmt">{{ translate('Manage Campaigns') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_content_view" class="form-check-input" id="ug_cc_cont_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_cont_view">{{ translate('View Content') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_content_manage" class="form-check-input" id="ug_cc_cont_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_cont_mgmt">{{ translate('Manage Content') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_earnings_view" class="form-check-input" id="ug_cc_earn_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_earn_view">{{ translate('View Earnings') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_payouts_manage" class="form-check-input" id="ug_cc_payouts"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_payouts">{{ translate('Manage Payouts') }} <span class="text-danger">*</span></label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_leads_view" class="form-check-input" id="ug_cc_leads_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_leads_view">{{ translate('View Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_leads_manage" class="form-check-input" id="ug_cc_leads_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_leads_mgmt">{{ translate('Manage Leads') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_reports_view" class="form-check-input" id="ug_cc_reports"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_reports">{{ translate('View Reports') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_creator_ai_tools_use" class="form-check-input" id="ug_cc_ai"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_cc_ai">{{ translate('Use AI Creator Tools') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_community_marketplace_view" class="form-check-input" id="ug_com_view"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_com_view">{{ translate('View Community Marketplace') }}</label></div></div>
                                                    <div class="check-item m-0 p-0"><div class="form-group m-0 form-check form--check"><input type="checkbox" name="modules[]" value="urban_goodz_community_marketplace_manage" class="form-check-input" id="ug_com_mgmt"><label class="form-check-label ps--3 qcont text-dark opacity-70" for="ug_com_mgmt">{{ translate('Manage Community Marketplace') }}</label></div></div>
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
                                                       id="trip">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="trip">{{translate('messages.Trip')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="promotion" class="form-check-input"
                                                       id="promotion">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="promotion">{{translate('messages.Promotion')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input"
                                                       id="vehicle">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="vehicle">{{translate('messages.Vehicle')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="provider" class="form-check-input"
                                                       id="provider">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="provider">{{translate('messages.Provider')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="driver" class="form-check-input"
                                                       id="driver">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="driver">{{translate('messages.Driver')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="download_app" class="form-check-input"
                                                       id="download_app">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="download_app">{{translate('messages.Download app')}}</label>
                                            </div>
                                        </div>
                                        <div class="check-item">
                                            <div class="form-group form-check form--check">
                                                <input type="checkbox" name="modules[]" value="rental_report" class="form-check-input"
                                                       id="rental_report">
                                                <label class="form-check-label ps--3 qcont text-dark opacity-70" for="rental_report">{{translate('messages.Report')}}</label>
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
                                               id="heat_map">
                                        <label class="form-check-label qcont text-dark" for="heat_map">{{translate('messages.heat_map')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="fleet_view" class="form-check-input"
                                               id="fleet_view">
                                        <label class="form-check-label qcont text-dark" for="fleet_view">{{translate('messages.fleet_view')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride" class="form-check-input"
                                               id="ride">
                                        <label class="form-check-label qcont text-dark" for="ride">{{translate('messages.ride')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_promotion" class="form-check-input"
                                               id="ride_promotion">
                                        <label class="form-check-label qcont text-dark" for="ride_promotion">{{translate('messages.promotion')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="fare" class="form-check-input"
                                               id="fare">
                                        <label class="form-check-label qcont text-dark" for="fare">{{translate('messages.fare')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_vehicle" class="form-check-input"
                                               id="ride_vehicle">
                                        <label class="form-check-label qcont text-dark" for="ride_vehicle">{{translate('messages.vehicle')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="rider" class="form-check-input"
                                               id="rider">
                                        <label class="form-check-label qcont text-dark" for="rider">{{translate('messages.rider')}}</label>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="form-group form-check form--check">
                                        <input type="checkbox" name="modules[]" value="ride_report" class="form-check-input"
                                               id="ride_report">
                                        <label class="form-check-label qcont text-dark" for="ride_report">{{translate('messages.report')}}</label>
                                    </div>
                                </div>
                            </div>
                        @endif

                            </div>
                        </div>

                        <div class="btn--container justify-content-end mt-4">
                            <button type="reset" id="reset-btn" class="btn min-w-120 btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary min-w-120">{{translate('messages.submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{translate('messages.roles_table')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$roles->total()}}</span>
                        </h5>
                        <form class="search-form min--200">
                            <!-- Search -->
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search"  value="{{request()?->search}}" class="form-control" placeholder="{{translate('ex_:_search_role_name')}}" aria-label="Search">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                            <!-- End Search -->
                        </form>
                        <!-- Unfold -->
                        {{-- <div class="hs-unfold">
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
                                href="{{route('admin.users.customer.wallet.export', ['type'=>'excel',request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                        alt="Image Description">
                                    {{ translate('messages.excel') }}
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                href="{{route('admin.users.customer.wallet.export', ['type'=>'csv',request()->getQueryString()])}}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="Image Description">
                                    {{ translate('messages.csv') }}
                                </a>
                            </div>
                        </div> --}}
                        <!-- End Unfold -->

                    </div>
                </div>
                <div class="card-body pt-0 pb-0">
                    <div class="table-responsive datatable-custom">
                        <table  class="role--table table table-borderless table-thead-bordered table-align-middle card-table" >
                            <thead class="thead-light">
                            <tr>
                                <th scope="col" class="border-0 min-w--120">{{translate('sl')}}</th>
                                <th scope="col" class="border-0">{{translate('messages.role_name')}}</th>
                                <th scope="col" class="border-0">{{translate('messages.Permissions')}}</th>
                                <th scope="col" class="border-0">{{translate('messages.created_at')}}</th>
                                <th scope="col" class="border-0 text-center">{{translate('messages.action')}}</th>
                            </tr>
                            </thead>
                            <tbody  id="set-rows">
                            @foreach($roles as $k=>$role)
                                <tr>
                                    <td scope="row" class="text-dark">{{$k+$roles->firstItem()}}</td>
                                    <td title="{{ $role['name'] }}" >
                                        <div class="min-w-220 line--limit-1 max-w--220px text-dark">
                                            {{Str::limit($role['name'],25,'...')}}
                                        </div>
                                    </td>
                                    <td class="text-capitalize text-dark">
                                          @php
                                                $permissions = json_decode($role->modules, true)??[];
                                            @endphp
                                        {{ count($permissions) }}
                                    </td>
                                    <td>
                                        <div class="create-date text-dark">
                                            {{\App\CentralLogics\Helpers::date_format($role['created_at'])}}
                                        </div>
                                    </td>

                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn btn-theme-dark btn-outline-base offcanvas-trigger data-info-show"
                                                data-id="{{$role['id']}}" data-url="{{route('admin.users.custom-role.view',[$role['id']])}}"
                                                href="#0" data-target="#offcanvas__role_table">
                                                <i class="tio-visible"></i>
                                            </a>

                                            <a class="btn action-btn btn-theme btn-outline-base"
                                                href="{{route('admin.users.custom-role.edit',[$role['id']])}}" title="{{translate('messages.edit_role')}}"><i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn btn--danger btn-outline-danger " href="javascript:"
                                            data-toggle="modal"
                                        data-target="#confirmation-deletes-{{ $role['id'] }}"  data-id="role-{{$role['id']}}" data-message="{{translate('messages.Want_to_delete_this_role')}}"
                                               title="{{translate('messages.delete_role')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                        </div>




                            <div class="modal shedule-modal fade" id="confirmation-deletes-{{ $role['id'] }}" tabindex="-1" aria-labelledby="exampleModalLabel"
                                aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content pb-2 max-w-500">
                                         <form action="{{route('admin.users.custom-role.delete',[$role['id']])}}"
                                                method="post" id="role-{{$role['id']}}">
                                                 @csrf @method('delete')
                                        <div class="modal-header">
                                            <button type="button"
                                                class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                                                data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="text-center">
                                                <img src="{{asset('public/assets/admin/img/delete.png')}}" alt="icon" class="mb-20">
                                                <h3 class="mb-2 fs-18">{{ translate('Are you sure ?') }}</h3>
                                                <p class="mb-2 px-3">{{ translate('Want to delete this role') }}</p>
                                            </div>
                                        </div>
                                        <div class="modal-footer justify-content-center border-0 pt-0 mb-1 gap-2">
                                            <button type="submit" class="btn min-w-120px btn-danger min-h-45px">{{ translate('yes') }}</button>
                                            <button type="button" data-dismiss="modal" class="btn min-w-120px btn--reset min-h-45px" data-dismiss="modal">{{ translate('No') }}</button>
                                        </div>
                                        </form>
                                    </div>
                                </div>
                            </div>



                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($roles) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $roles->links() !!}
                    </div>
                    @if(count($roles) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('no_data_found')}}
                        </h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>








</div>




<div id="offcanvas__role_table" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div>
        <div id="data-view" class="h-100">  </div>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/custom-role-index.js')}}"></script>

<script>
        $(document).on('click', '.data-info-show', function() {
            let id = $(this).data('id');
            let url = $(this).data('url');
            $('#content-disable').addClass('disabled');
            fetch_data(id, url)
        })

        function fetch_data(id, url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show();

                },
                success: function(data) {
                    $("#data-view").append(data.view);
                         initSelect2Dropdowns();
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }
       function initSelect2Dropdowns() {
            $('.offcanvas-close, #offcanvasOverlay').on('click', function () {
                    $('.custom-offcanvas').removeClass('open');
                    $('#offcanvasOverlay').removeClass('show');
                    $('#content-disable').removeClass('disabled');
                });
        }

</script>
@endpush
