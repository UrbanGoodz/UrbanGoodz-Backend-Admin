<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzRentalBooking;
use App\Models\UrbanGoodzRentalInspection;
use App\CentralLogics\Helpers;
use App\Services\UrbanGoodzPaymentService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzRentalController extends Controller
{
    public function dashboard()
    {
        return view('admin-views.urban-goodz.rentals.dashboard', [
            'totalAssets' => UrbanGoodzRentalAsset::count(),
            'availableAssets' => UrbanGoodzRentalAsset::where('status', 'available')->where('is_active', true)->count(),
            'activeBookings' => UrbanGoodzRentalBooking::whereIn('status', ['approved', 'active', 'picked_up'])->count(),
            'pendingBookings' => UrbanGoodzRentalBooking::where('status', 'pending')->count(),
            'pendingInspections' => UrbanGoodzRentalInspection::where('status', 'pending')->count(),
            'damageReports' => UrbanGoodzRentalInspection::where('damage_found', true)->count(),
            'recentAssets' => UrbanGoodzRentalAsset::latest()->take(5)->get(),
            'recentBookings' => UrbanGoodzRentalBooking::with('asset')->latest()->take(5)->get(),
            'businessTypes' => \App\Models\UrbanGoodzBusinessType::whereIn('slug', [
                'car_rental', 'vehicle_rental', 'equipment_rental', 'rental_provider',
            ])->pluck('name', 'slug'),
        ]);
    }

    public function rentals(Request $request)
    {
        return $this->dashboard();
    }

    public function carRental(Request $request)
    {
        return $this->assetsByType('car_rental');
    }

    public function vehicleRental(Request $request)
    {
        return $this->assetsByType('vehicle_rental');
    }

    public function equipmentRental(Request $request)
    {
        return $this->assetsByType('equipment_rental');
    }

    private function assetsByType(string $type)
    {
        $assets = UrbanGoodzRentalAsset::where('business_type_slug', $type)
            ->latest()->paginate(25);

        return view('admin-views.urban-goodz.rentals.assets.index', [
            'assets' => $assets,
            'type' => $type,
            'typeLabel' => match($type) {
                'car_rental' => 'Car Rental',
                'vehicle_rental' => 'Vehicle Rental',
                'equipment_rental' => 'Equipment Rental',
                default => 'Rentals',
            },
        ]);
    }

    // ---- ASSET CRUD ----

    public function assetsIndex(Request $request)
    {
        $query = UrbanGoodzRentalAsset::query();

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }
        if ($request->filled('business_type_slug')) {
            $query->where('business_type_slug', $request->business_type_slug);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('make', 'like', '%' . $request->search . '%')
                    ->orWhere('model', 'like', '%' . $request->search . '%')
                    ->orWhere('plate_number', 'like', '%' . $request->search . '%');
            });
        }

        $assets = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.rentals.assets.index', compact('assets'));
    }

    public function assetsCreate()
    {
        $businessTypes = \App\Models\UrbanGoodzBusinessType::whereIn('slug', [
            'car_rental', 'vehicle_rental', 'equipment_rental', 'rental_provider',
        ])->pluck('name', 'slug');

        return view('admin-views.urban-goodz.rentals.assets.create', compact('businessTypes'));
    }

    public function assetsStore(Request $request)
    {
        $data = $request->validate([
            'business_type_slug' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'asset_type' => ['required', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:4'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'vin' => ['nullable', 'string', 'max:50'],
            'unit_number' => ['nullable', 'string', 'max:50'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'mileage_limit' => ['nullable', 'integer', 'min:0'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['status'] = 'available';

        $photos = [];
        foreach ($request->file('photos', []) as $photo) {
            $photos[] = ['img' => Helpers::upload('rental_assets/', 'png', $photo), 'storage' => Helpers::getDisk()];
        }
        $data['photos'] = json_encode($photos);

        UrbanGoodzRentalAsset::create($data);

        return redirect()->route('admin.urban-goodz.rentals.assets.index')
            ->with('success', translate('Rental asset created successfully.'));
    }

    public function assetsEdit($id)
    {
        $asset = UrbanGoodzRentalAsset::findOrFail($id);
        $businessTypes = \App\Models\UrbanGoodzBusinessType::whereIn('slug', [
            'car_rental', 'vehicle_rental', 'equipment_rental', 'rental_provider',
        ])->pluck('name', 'slug');

        return view('admin-views.urban-goodz.rentals.assets.edit', compact('asset', 'businessTypes'));
    }

    public function assetsUpdate(Request $request, $id)
    {
        $asset = UrbanGoodzRentalAsset::findOrFail($id);

        $data = $request->validate([
            'business_type_slug' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'asset_type' => ['required', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'string', 'max:4'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'vin' => ['nullable', 'string', 'max:50'],
            'unit_number' => ['nullable', 'string', 'max:50'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'mileage_limit' => ['nullable', 'integer', 'min:0'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $existingPhotos = json_decode($asset->photos ?? '[]', true) ?: [];
        $keptPhotos = [];
        $removeIndexes = $request->input('remove_photos', []);
        foreach ($existingPhotos as $index => $photo) {
            if (in_array($index, $removeIndexes)) {
                Helpers::check_and_delete('rental_assets/', $photo['img'] ?? '');
                continue;
            }
            $keptPhotos[] = $photo;
        }
        foreach ($request->file('photos', []) as $photo) {
            $keptPhotos[] = ['img' => Helpers::upload('rental_assets/', 'png', $photo), 'storage' => Helpers::getDisk()];
        }
        $data['photos'] = json_encode($keptPhotos);

        $asset->update($data);

        return redirect()->route('admin.urban-goodz.rentals.assets.index')
            ->with('success', translate('Rental asset updated successfully.'));
    }

    public function assetsDestroy($id)
    {
        UrbanGoodzRentalAsset::findOrFail($id)->bookings()->delete();
        UrbanGoodzRentalAsset::findOrFail($id)->delete();

        return back()->with('success', translate('Rental asset deleted successfully.'));
    }

    public function assetsStatus($id, $status)
    {
        $asset = UrbanGoodzRentalAsset::findOrFail($id);
        $asset->is_active = $status === '1';
        $asset->save();

        return back()->with('success', translate('Asset status updated.'));
    }

    // ---- BOOKINGS CRUD ----

    public function bookingsIndex(Request $request)
    {
        $query = UrbanGoodzRentalBooking::with('asset');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $request->search . '%');
            });
        }

        $bookings = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.rentals.bookings.index', compact('bookings'));
    }

    public function bookingsCreate()
    {
        $assets = UrbanGoodzRentalAsset::where('is_active', true)->orderBy('title')->get();

        return view('admin-views.urban-goodz.rentals.bookings.create', compact('assets'));
    }

    public function bookingsStore(Request $request)
    {
        $data = $request->validate([
            'rental_asset_id' => ['required', 'integer', 'exists:urban_goodz_rental_assets,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_notes' => ['nullable', 'string'],
        ]);

        $data['status'] = 'pending';
        $data['payment_status'] = 'pending';
        $data['deposit_status'] = 'pending';
        $data['verification_status'] = 'pending';

        UrbanGoodzRentalBooking::create($data);

        return redirect()->route('admin.urban-goodz.rentals.bookings.index')->with('success', translate('Booking created.'));
    }

    public function bookingsShow($id)
    {
        $booking = UrbanGoodzRentalBooking::with(['asset', 'inspections'])->findOrFail($id);

        return view('admin-views.urban-goodz.rentals.bookings.show', compact('booking'));
    }

    public function bookingsStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string|max:100']);
        $booking = UrbanGoodzRentalBooking::findOrFail($id);
        $allowed = ['pending', 'approved', 'declined', 'active', 'picked_up', 'returned', 'completed', 'cancelled'];

        abort_unless(in_array($request->status, $allowed), 400);

        $booking->status = $request->status;
        $booking->save();

        return back()->with('success', translate('Booking status updated.'));
    }

    public function bookingsVerification(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_rental_verification_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $request->validate(['status' => 'required|string|max:100']);
        $booking = UrbanGoodzRentalBooking::findOrFail($id);
        $allowed = ['pending', 'verified', 'failed'];

        abort_unless(in_array($request->status, $allowed), 400);

        $booking->verification_status = $request->status;
        $booking->save();

        return back()->with('success', translate('Verification status updated.'));
    }

    public function bookingsPayment(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_rental_bookings_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $request->validate(['status' => 'required|string|max:100']);
        $booking = UrbanGoodzRentalBooking::findOrFail($id);
        $allowed = ['pending', 'paid', 'refunded', 'failed'];

        abort_unless(in_array($request->status, $allowed), 400);

        $booking->payment_status = $request->status;
        $booking->save();

        return back()->with('success', translate('Payment status updated.'));
    }

    public function bookingsDeposit(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_rental_deposits_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $request->validate(['status' => 'required|string|max:100']);
        $booking = UrbanGoodzRentalBooking::findOrFail($id);
        $allowed = ['pending', 'collected', 'released', 'partially_released', 'forfeited'];

        abort_unless(in_array($request->status, $allowed), 400);

        $booking->deposit_status = $request->status;
        $booking->save();

        return back()->with('success', translate('Deposit status updated.'));
    }

    public function bookingsNotes(Request $request, $id)
    {
        $data = $request->validate(['admin_notes' => ['nullable', 'string']]);
        UrbanGoodzRentalBooking::findOrFail($id)->update($data);

        return back()->with('success', translate('Notes updated.'));
    }

    public function bookingsDestroy($id)
    {
        UrbanGoodzRentalBooking::findOrFail($id)->delete();

        return back()->with('success', translate('Booking deleted.'));
    }

    // ---- INSPECTIONS CRUD ----

    public function inspectionsIndex(Request $request)
    {
        $query = UrbanGoodzRentalInspection::with('booking.asset');

        if ($request->filled('inspection_type')) {
            $query->where('inspection_type', $request->inspection_type);
        }
        if ($request->filled('damage_found')) {
            $query->where('damage_found', $request->damage_found === '1');
        }

        $inspections = $query->latest()->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.rentals.inspections.index', compact('inspections'));
    }

    public function inspectionsCreate(Request $request)
    {
        $bookingId = $request->booking_id;
        $booking = $bookingId ? UrbanGoodzRentalBooking::with('asset')->find($bookingId) : null;
        $activeBookings = UrbanGoodzRentalBooking::with('asset')
            ->whereIn('status', ['approved', 'active', 'picked_up'])
            ->get();

        return view('admin-views.urban-goodz.rentals.inspections.create', compact('booking', 'activeBookings'));
    }

    public function inspectionsStore(Request $request)
    {
        $data = $request->validate([
            'rental_booking_id' => ['required', 'integer', 'exists:urban_goodz_rental_bookings,id'],
            'inspection_type' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'damage_found' => ['boolean'],
            'damage_amount' => ['nullable', 'numeric', 'min:0'],
            'inspected_by' => ['nullable', 'string', 'max:255'],
        ]);

        $data['damage_found'] = $request->boolean('damage_found');
        $data['status'] = 'completed';

        UrbanGoodzRentalInspection::create($data);

        return redirect()->route('admin.urban-goodz.rentals.inspections.index')
            ->with('success', translate('Inspection created successfully.'));
    }

    public function inspectionsEdit($id)
    {
        $inspection = UrbanGoodzRentalInspection::with('booking.asset')->findOrFail($id);

        return view('admin-views.urban-goodz.rentals.inspections.edit', compact('inspection'));
    }

    public function inspectionsUpdate(Request $request, $id)
    {
        $inspection = UrbanGoodzRentalInspection::findOrFail($id);

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'damage_found' => ['boolean'],
            'damage_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:100'],
        ]);

        $data['damage_found'] = $request->boolean('damage_found');

        $inspection->update($data);

        return redirect()->route('admin.urban-goodz.rentals.inspections.index')
            ->with('success', translate('Inspection updated successfully.'));
    }

    public function inspectionsDestroy($id)
    {
        UrbanGoodzRentalInspection::findOrFail($id)->delete();

        return back()->with('success', translate('Inspection deleted.'));
    }
}
