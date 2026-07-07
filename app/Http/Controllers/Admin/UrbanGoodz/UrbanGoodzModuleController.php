<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceRequest;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzAppointment;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\UrbanGoodzCommunityComment;
use App\Models\UrbanGoodzCommunityMarketplaceItem;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorProduct;
use App\Models\UrbanGoodzLogisticsJob;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzMedicalCourierCustodyLog;
use App\Models\UrbanGoodzEarnMoneyOpportunity;
use App\Models\UrbanGoodzEarnMoneyApplication;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzPlusMembership;
use App\Models\UrbanGoodzSpotlightBusiness;
use App\Models\UrbanGoodzDiscoverySearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzModuleController extends Controller
{
    protected array $modules = [];

    public function __construct()
    {
        $this->modules = [
            'book-anything' => [
                'model' => UrbanGoodzServiceRequest::class,
                'label' => 'Service Requests',
                'icon' => 'tio-calendar',
                'permission' => 'urban_goodz_book_anything',
                'columns' => ['customer_name', 'service_type', 'status', 'location'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['pending', 'in_progress', 'completed', 'cancelled'],
            ],
            'service-providers' => [
                'model' => UrbanGoodzServiceProvider::class,
                'label' => 'Service Providers',
                'icon' => 'tio-user',
                'permission' => 'urban_goodz_book_anything',
                'columns' => ['business_name', 'service_category', 'is_verified', 'is_active'],
                'sort' => 'business_name',
                'dir' => 'asc',
            ],
            'appointments' => [
                'model' => UrbanGoodzAppointment::class,
                'label' => 'Appointments',
                'icon' => 'tio-clock',
                'permission' => 'urban_goodz_book_anything',
                'columns' => ['scheduled_at', 'status', 'service_request_id'],
                'sort' => 'scheduled_at',
                'dir' => 'desc',
            ],
            'community' => [
                'model' => UrbanGoodzCommunityPost::class,
                'label' => 'Community Posts',
                'icon' => 'tio-users',
                'permission' => 'urban_goodz_community',
                'columns' => ['title', 'type', 'is_published', 'author_name'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['draft', 'published'],
            ],
            'community-comments' => [
                'model' => UrbanGoodzCommunityComment::class,
                'label' => 'Comments',
                'icon' => 'tio-comment',
                'permission' => 'urban_goodz_community',
                'columns' => ['author_name', 'body', 'is_approved', 'created_at'],
                'sort' => 'created_at',
                'dir' => 'desc',
            ],
            'community-marketplace' => [
                'model' => UrbanGoodzCommunityMarketplaceItem::class,
                'label' => 'Marketplace Items',
                'icon' => 'tio-shop',
                'permission' => 'urban_goodz_community',
                'columns' => ['title', 'price', 'status', 'seller_name'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['available', 'sold', 'reserved'],
            ],
            'creator-commerce' => [
                'model' => UrbanGoodzCreatorApplication::class,
                'label' => 'Creator Applications',
                'icon' => 'tio-star',
                'permission' => 'urban_goodz_creator_commerce',
                'columns' => ['creator_name', 'platform', 'status', 'follower_count'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['pending', 'approved', 'rejected'],
            ],
            'creator-products' => [
                'model' => UrbanGoodzCreatorProduct::class,
                'label' => 'Creator Products',
                'icon' => 'tio-gift',
                'permission' => 'urban_goodz_creator_commerce',
                'columns' => ['name', 'price', 'status', 'is_active'],
                'sort' => 'created_at',
                'dir' => 'desc',
            ],
            'logistics' => [
                'model' => UrbanGoodzLogisticsJob::class,
                'label' => 'Logistics Jobs',
                'icon' => 'tio-truck',
                'permission' => 'urban_goodz_logistics',
                'columns' => ['job_number', 'pickup_location', 'delivery_location', 'status'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['pending', 'assigned', 'in_transit', 'delivered', 'cancelled'],
            ],
            'medical-courier' => [
                'model' => UrbanGoodzMedicalCourierJob::class,
                'label' => 'Medical Courier Jobs',
                'icon' => 'tio-medical',
                'permission' => 'urban_goodz_medical_courier',
                'columns' => ['job_number', 'pickup_location', 'delivery_location', 'specimen_type', 'status'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['pending', 'assigned', 'in_transit', 'delivered', 'cancelled'],
            ],
            'medical-courier-logs' => [
                'model' => UrbanGoodzMedicalCourierCustodyLog::class,
                'label' => 'Custody Logs',
                'icon' => 'tio-document',
                'permission' => 'urban_goodz_medical_courier',
                'columns' => ['job_id', 'action', 'handler_name', 'logged_at'],
                'sort' => 'logged_at',
                'dir' => 'desc',
            ],
            'earn-money' => [
                'model' => UrbanGoodzEarnMoneyOpportunity::class,
                'label' => 'Earn Money Opportunities',
                'icon' => 'tio-money',
                'permission' => 'urban_goodz_earn_money',
                'columns' => ['title', 'type', 'reward_amount', 'status'],
                'sort' => 'created_at',
                'dir' => 'desc',
                'statuses' => ['active', 'inactive', 'expired'],
            ],
            'earn-money-applications' => [
                'model' => UrbanGoodzEarnMoneyApplication::class,
                'label' => 'Earn Money Applications',
                'icon' => 'tio-user',
                'permission' => 'urban_goodz_earn_money',
                'columns' => ['applicant_name', 'opportunity_id', 'status'],
                'sort' => 'created_at',
                'dir' => 'desc',
            ],
            'events' => [
                'model' => UrbanGoodzEvent::class,
                'label' => 'Events',
                'icon' => 'tio-calendar',
                'permission' => 'urban_goodz_events',
                'columns' => ['title', 'starts_at', 'location', 'status'],
                'sort' => 'starts_at',
                'dir' => 'desc',
                'statuses' => ['draft', 'published', 'cancelled', 'completed'],
            ],
            'plus' => [
                'model' => UrbanGoodzPlusMembership::class,
                'label' => 'Urban Goodz+ Memberships',
                'icon' => 'tio-crown',
                'permission' => 'urban_goodz_plus',
                'columns' => ['member_name', 'member_email', 'tier', 'status'],
                'sort' => 'created_at',
                'dir' => 'desc',
            ],
            'spotlight' => [
                'model' => UrbanGoodzSpotlightBusiness::class,
                'label' => 'Black-Owned Spotlight',
                'icon' => 'tio-star',
                'permission' => 'urban_goodz_spotlight',
                'columns' => ['business_name', 'category', 'is_featured', 'is_active'],
                'sort' => 'business_name',
                'dir' => 'asc',
            ],
            'discovery' => [
                'model' => UrbanGoodzDiscoverySearch::class,
                'label' => 'Discovery Searches',
                'icon' => 'tio-search',
                'permission' => 'urban_goodz_discovery',
                'columns' => ['query', 'source', 'result_count', 'was_fulfilled'],
                'sort' => 'created_at',
                'dir' => 'desc',
            ],
        ];
    }

    public function index(Request $request, string $section)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];

        $query = $model::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $config) {
                foreach (array_slice($config['columns'], 0, 3) as $col) {
                    $q->orWhere($col, 'like', '%' . $search . '%');
                }
            });
        }

        if ($request->filled('status') && isset($config['statuses'])) {
            $query->where('status', $request->status);
        }

        $items = $query->orderBy($config['sort'], $config['dir'])->paginate(25)->appends($request->query());

        $title = $config['label'];
        $columns = $config['columns'];
        $statuses = $config['statuses'] ?? [];

        return view('admin-views.urban-goodz.modules.index', compact('items', 'title', 'columns', 'section', 'config', 'statuses'));
    }

    public function create(string $section)
    {
        $config = $this->getConfig($section);
        $title = $config['label'];

        return view('admin-views.urban-goodz.modules.create', compact('title', 'section', 'config'));
    }

    public function store(Request $request, string $section)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];

        $rules = $this->getValidationRules($config, null);
        $data = $request->validate($rules);

        if (method_exists($model, 'creating')) {
            $data = $this->prepareData($config, $data);
        }

        $model::create($data);

        return redirect()->route('admin.urban-goodz.modules.index', $section)
            ->with('success', translate($config['label'] . ' created successfully.'));
    }

    public function edit(string $section, $id)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];
        $item = $model::findOrFail($id);
        $title = $config['label'];

        return view('admin-views.urban-goodz.modules.edit', compact('item', 'title', 'section', 'config'));
    }

    public function update(Request $request, string $section, $id)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];
        $item = $model::findOrFail($id);

        $rules = $this->getValidationRules($config, $item->id);
        $data = $request->validate($rules);

        $item->update($data);

        return redirect()->route('admin.urban-goodz.modules.index', $section)
            ->with('success', translate($config['label'] . ' updated successfully.'));
    }

    public function destroy(string $section, $id)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];
        $model::findOrFail($id)->delete();

        return back()->with('success', translate($config['label'] . ' deleted successfully.'));
    }

    public function status(string $section, $id, $status)
    {
        $config = $this->getConfig($section);
        $model = $config['model'];
        $item = $model::findOrFail($id);

        if (in_array('is_active', $config['columns'])) {
            $item->is_active = $status === '1';
        } elseif (in_array('status', $config['columns']) || isset($config['statuses'])) {
            $item->status = $status;
            $item->save();
            return back()->with('success', translate('Status updated.'));
        }

        $item->save();

        return back()->with('success', translate('Status updated successfully.'));
    }

    private function getConfig(string $section): array
    {
        abort_unless(isset($this->modules[$section]), 404);
        return $this->modules[$section];
    }

    private function getValidationRules(array $config, $ignoreId = null): array
    {
        $rules = [];

        $model = new $config['model'];
        $fillable = $model->getFillable();

        foreach ($fillable as $field) {
            if ($field === 'id' || $field === 'timestamps') continue;

            $fieldRules = ['nullable'];

            if (in_array($field, ['job_number'])) {
                $unique = Rule::unique($model->getTable(), $field);
                if ($ignoreId) $unique->ignore($ignoreId);
                $fieldRules = ['required', 'string', $unique];
            } elseif (in_array($field, ['title', 'name', 'business_name', 'creator_name', 'query', 'member_name'])) {
                $fieldRules = ['required', 'string', 'max:255'];
            } elseif (in_array($field, ['email', 'member_email', 'applicant_email'])) {
                $fieldRules = ['nullable', 'email', 'max:255'];
            } elseif (in_array($field, ['price', 'reward_amount', 'monthly_fee', 'offer_amount', 'ticket_price'])) {
                $fieldRules = ['nullable', 'numeric', 'min:0'];
            } elseif (in_array($field, ['status', 'tier', 'specimen_type'])) {
                $fieldRules = ['nullable', 'string', 'max:100'];
            } elseif (in_array($field, ['description', 'body', 'bio', 'terms', 'admin_notes'])) {
                $fieldRules = ['nullable', 'string'];
            } elseif (in_array($field, ['is_active', 'is_published', 'is_featured', 'is_verified', 'is_approved', 'was_fulfilled', 'requires_refrigeration', 'is_biological_hazard'])) {
                $fieldRules = ['boolean'];
            }

            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    private function prepareData(array $config, array $data): array
    {
        $model = $config['model'];

        if (isset($data['job_number']) && empty($data['job_number'])) {
            $prefix = str_starts_with($config['label'], 'Medical') ? 'MC' : 'LOG';
            $data['job_number'] = $prefix . '-' . strtoupper(substr(uniqid(), -8));
        }

        return $data;
    }
}
