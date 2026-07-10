@extends('layouts.admin.app')

@section('title', translate('Create Campaign'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Create Campaign') }}</h1>
            <a href="{{ route('admin.urban-goodz.creator.campaigns') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Back') }}</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.creator.campaigns.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{ translate('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ translate('Type') }} <span class="text-danger">*</span></label>
                                <select name="type" class="form-control" required>
                                    <option value="product_review">{{ translate('Product Review') }}</option>
                                    <option value="vendor_spotlight">{{ translate('Vendor Spotlight') }}</option>
                                    <option value="food_review">{{ translate('Food Review') }}</option>
                                    <option value="event_promo">{{ translate('Event Promotion') }}</option>
                                    <option value="rental_showcase">{{ translate('Rental Showcase') }}</option>
                                    <option value="fashion_styling">{{ translate('Fashion Styling') }}</option>
                                    <option value="service_provider">{{ translate('Service Provider Feature') }}</option>
                                    <option value="business_launch">{{ translate('Business Launch') }}</option>
                                    <option value="order_anywhere_discovery">{{ translate('Order Anywhere Discovery') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ translate('Category') }}</label>
                                <input type="text" name="category" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>{{ translate('Vendor') }}</label>
                                <select name="vendor_id" class="form-control">
                                    <option value="">{{ translate('Select Vendor') }}</option>
                                    @foreach($vendors as $v)
                                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('City') }}</label>
                                <input type="text" name="city" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('Zone') }}</label>
                                <input type="text" name="zone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('Pay Type') }} <span class="text-danger">*</span></label>
                                <select name="pay_type" class="form-control" required>
                                    <option value="flat">{{ translate('Flat') }}</option>
                                    <option value="commission">{{ translate('Commission') }}</option>
                                    <option value="hybrid">{{ translate('Hybrid') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('Flat Payout') }}</label>
                                <input type="number" name="flat_payout" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('Commission Rate (%)') }}</label>
                                <input type="number" name="commission_rate" class="form-control" step="0.01" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>{{ translate('Deadline') }}</label>
                                <input type="date" name="deadline" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Brief') }}</label>
                                <textarea name="brief" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>{{ translate('Deliverables') }}</label>
                                <textarea name="deliverables" class="form-control" rows="3" placeholder="{{ translate('List expected deliverables') }}"></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="draft">{{ translate('Draft') }}</option>
                                    <option value="open">{{ translate('Open') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ translate('Create Campaign') }}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
