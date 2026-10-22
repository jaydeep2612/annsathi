<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\User;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.admin-dashboard';
    protected static ?string $navigationLabel = 'Admin Dashboard';
    protected static ?string $title = 'Admin Dashboard Control';

    public static function canAccess(): bool
    {
        return auth()->check()
            && auth()->user()->restaurant_id
            && auth()->user()->isRestaurantAdmin();
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $rid = $user->restaurant_id;
        $restaurant = $user->restaurant;

        // STAFF COUNT
        $totalStaff = User::where('restaurant_id', $rid)->count();

        // CATEGORIES COUNT
        $totalCategories = Category::where('restaurant_id', $rid)->count();

        // MENU ITEMS COUNT 
        $totalItems = MenuItem::where('restaurant_id', $rid)->count();

        // REVENUE METRICS
        $basePaymentQuery = Payment::whereHas('order', function ($query) use ($rid) {
            $query->where('restaurant_id', $rid);
        })->where('status', 'paid');

        $totalRevenue = (clone $basePaymentQuery)->sum('amount');
        $todayRevenue = (clone $basePaymentQuery)->whereDate('paid_at', Carbon::today())->sum('amount');
        $monthRevenue = (clone $basePaymentQuery)->where('paid_at', '>=', Carbon::now()->startOfMonth())->sum('amount');

        // ORDERS METRICS
        $baseOrderQuery = Order::where('restaurant_id', $rid);
        $totalOrdersCount = (clone $baseOrderQuery)->count();
        $todayOrders = (clone $baseOrderQuery)->whereDate('created_at', Carbon::today())->count();
        $monthOrders = (clone $baseOrderQuery)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        // TABLES METRICS
        $baseTableQuery = RestaurantTable::where('restaurant_id', $rid);
        $totalTablesCount = (clone $baseTableQuery)->count();
        $activeTablesCount = (clone $baseTableQuery)->whereIn('status', ['occupied', 'bill_pending'])->count();

        // ---------------------------------------------------
        // CHART DATA: 30-Day Revenue Trend (Line Graph)
        // ---------------------------------------------------
        $dates = [];
        $startDate = Carbon::today()->subDays(29);
        for ($i = 0; $i < 30; $i++) {
            $dates[] = $startDate->copy()->addDays($i)->format('Y-m-d');
        }

        $dailyRevenueQuery = Payment::whereHas('order', function ($query) use ($rid) {
            $query->where('restaurant_id', $rid);
        })
            ->where('status', 'paid')
            ->where('paid_at', '>=', $startDate->startOfDay())
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $dataPoints = [];
        foreach ($dates as $date) {
            $dataPoints[] = $dailyRevenueQuery[$date] ?? 0;
        }

        $chartSeries = [
            [
                'name' => 'Total Revenue',
                'data' => $dataPoints
            ]
        ];

        $formattedDates = array_map(function ($date) {
            return Carbon::parse($date)->format('d M');
        }, $dates);

        // ---------------------------------------------------
        // RECENT ORDERS LIVE FEED (Latest 8)
        // ---------------------------------------------------
        $recentOrders = Order::where('restaurant_id', $rid)
            ->with(['items', 'table'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        // ---------------------------------------------------
        // TOP SELLING MENU ITEMS (Top 5)
        // ---------------------------------------------------
        $topSellingItems = \App\Models\OrderItem::whereHas('order', function ($q) use ($rid) {
            $q->where('restaurant_id', $rid);
        })
            ->select('item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(quantity * unit_price) as total_sales'))
            ->groupBy('item_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return [
            'restaurant' => $restaurant,
            'totalBranches' => 0,
            'isRestaurantAdmin' => true,
            'isBranchAdmin' => false,
            'showBranchesWidget' => false,
            'totalStaff' => $totalStaff,
            'totalCategories' => $totalCategories,
            'totalItems' => $totalItems,
            'totalRevenue' => $totalRevenue,
            'todayRevenue' => $todayRevenue,
            'monthRevenue' => $monthRevenue,
            'totalOrdersCount' => $totalOrdersCount,
            'todayOrders' => $todayOrders,
            'monthOrders' => $monthOrders,
            'totalTablesCount' => $totalTablesCount,
            'activeTablesCount' => $activeTablesCount,
            'branchStats' => [],
            'donutSeries' => [],
            'donutLabels' => [],
            'chartDates' => $formattedDates,
            'chartSeries' => $chartSeries,
            'recentOrders' => $recentOrders,
            'topSellingItems' => $topSellingItems,
        ];
    }
}
