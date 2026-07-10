<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).



<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;
class ReservationController extends Controller
{
    public function home()
    {
        $setting = Setting::first();

        return view('home', compact('setting'));
    }

    public function peopleSelect()
    {
        return view('people');
    }

    public function showTimes($people)
    {
        if (!in_array((int)$people, [1, 2, 3, 4])) {
            abort(404);
        }

        $setting = Setting::first();

        if (!$setting) {
            abort(500, 'تنظیمات سیستم پیدا نشد.');
        }

        $baseDuration = (int)$setting->slot_duration;
        $duration = $baseDuration * (int)$people;
        $offDays = array_map('intval', explode(',', $setting->off_days));

        $availableSlots = [];

        $startDate = Carbon::today();

for ($i = 0; $i < 14; $i++) {
    $date = $startDate->copy()->addDays($i);

            if (in_array($date->dayOfWeek, $offDays)) {
                continue;
            }

            $dayStart = Carbon::parse($date->toDateString() . ' ' . $setting->start_time);
            $dayEnd = Carbon::parse($date->toDateString() . ' ' . $setting->end_time);

            $current = $dayStart->copy();

            while ($current->copy()->addMinutes($duration)->lte($dayEnd)) {
                $slotStart = $current->copy();
                $slotEnd = $current->copy()->addMinutes($duration);

                $hasConflict = $this->hasTimeConflict(
                    $date->toDateString(),
                    $slotStart->format('H:i:s'),
                    $slotEnd->format('H:i:s')
                );

                if (!$hasConflict) {
                    $availableSlots[$date->toDateString()][] = [
                        'start' => $slotStart->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                    ];
                }

                $current->addMinutes($baseDuration);
            }
        }

        return view('times', compact('availableSlots', 'people', 'duration'));
    }

    public function reserve(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:80',
            'phone' => ['required', 'regex:/^09[0-9]{9}$/'],

            'people' => 'required|integer|in:1,2,3,4',
            'date' => 'required|date',
            'time' => 'required',
        ], [
            'name.required' => 'نام را وارد کنید.',
            'phone.required' => 'شماره تماس را وارد کنید.',
            'people.in' => 'تعداد نفرات معتبر نیست.',
            'phone.regex' => 'شماره موبایل باید ۱۱ رقم باشد و با 09 شروع شود.',

        ]);

        $setting = Setting::first();

        if (!$setting) {
            return redirect()->route('home')->with('error', 'تنظیمات سیستم پیدا نشد.');
        }

        $people = (int)$request->people;
        $baseDuration = (int)$setting->slot_duration;
        $duration = $baseDuration * $people;

        $offDays = array_map('intval', explode(',', $setting->off_days));

        $reservationDate = Carbon::parse($request->date);
        $today = Carbon::today();
        $maxDate = Carbon::today()->addDays(13);

        if ($reservationDate->lt($today) || $reservationDate->gt($maxDate)) {
            return redirect()
                ->route('booking.failed')
                ->with('message', 'این تاریخ خارج از بازه مجاز رزرو است.');
        }

        if (in_array($reservationDate->dayOfWeek, $offDays)) {
            return redirect()
                ->route('booking.failed')
                ->with('message', 'این روز برای رزرو فعال نیست.');
        }

        $slotStart = Carbon::parse($request->date . ' ' . $request->time);
        $slotEnd = $slotStart->copy()->addMinutes($duration);

        $dayStart = Carbon::parse($request->date . ' ' . $setting->start_time);
        $dayEnd = Carbon::parse($request->date . ' ' . $setting->end_time);

        if ($slotStart->lt($dayStart) || $slotEnd->gt($dayEnd)) {
            return redirect()
                ->route('booking.failed')
                ->with('message', 'این ساعت خارج از بازه کاری آرایشگاه است.');
        }

        try {
            DB::transaction(function () use ($request, $slotStart, $slotEnd, $people) {
                $conflict = Reservation::where('date', $request->date)
                    ->where(function ($query) use ($slotStart, $slotEnd) {
                        $query
                            ->where('start_time', '<', $slotEnd->format('H:i:s'))
                            ->where('end_time', '>', $slotStart->format('H:i:s'));
                    })
                    ->lockForUpdate()
                    ->exists();

                if ($conflict) {
                    throw new \Exception('reserved');
                }

                Reservation::create([
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'people_count' => $people,
                    'date' => $request->date,
                    'start_time' => $slotStart->format('H:i:s'),
                    'end_time' => $slotEnd->format('H:i:s'),
                ]);
            });
        } catch (\Exception $e) {
            return redirect()
                ->route('booking.failed')
                ->with('message', 'این نوبت چند ثانیه پیش رزرو شد.');
        }

        return redirect()
            ->route('booking.success')
            ->with([
                'name' => $request->name,
                'date' => $request->date,
                'start_time' => $slotStart->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'people' => $people,
            ]);
    }

    public function success()
    {
        return view('success');
    }

    public function failed()
    {
        return view('failed');
    }

    private function hasTimeConflict($date, $startTime, $endTime)
    {
        return Reservation::where('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();
    }
    public function getAvailableSlots(Request $request)
{
    $request->validate([
        'date' => 'required|date',
        'people_count' => 'required|integer|min:1|max:4',
    ]);

    $date = $request->date;
    $peopleCount = (int) $request->people_count;

    $setting = Setting::first();

    if (!$setting) {
        return response()->json([
            'message' => 'تنظیمات سیستم پیدا نشد.'
        ], 422);
    }

    $slotDuration = (int) $setting->slot_duration;

    // مدت زمان کل نوبت بر اساس تعداد نفرات
    $totalDuration = $slotDuration * $peopleCount;

    $startTime = Carbon::parse($date . ' ' . $setting->start_time);
    $endTime = Carbon::parse($date . ' ' . $setting->end_time);

    // اگر off_days به صورت JSON ذخیره شده باشد
    $offDays = $setting->off_days;

    if (is_string($offDays)) {
        $decoded = json_decode($offDays, true);
        $offDays = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($offDays)) {
        $offDays = [];
    }

    /*
        Carbon dayOfWeek:
        0 = Sunday
        1 = Monday
        2 = Tuesday
        3 = Wednesday
        4 = Thursday
        5 = Friday
        6 = Saturday

        اگر در دیتابیس تعطیلی‌ها را مثلاً [4,5] ذخیره کرده باشی:
        پنجشنبه و جمعه تعطیل هستند.
    */
    $selectedDay = Carbon::parse($date);

    if (in_array($selectedDay->dayOfWeek, $offDays)) {
        return response()->json([]);
    }

    // محدودیت رزرو فقط تا ۱۴ روز آینده
    $today = Carbon::today();
    $maxDate = Carbon::today()->addDays(14);

    if ($selectedDay->lt($today) || $selectedDay->gt($maxDate)) {
        return response()->json([]);
    }

    $availableSlots = [];

    $current = $startTime->copy();

    while ($current->copy()->addMinutes($totalDuration)->lte($endTime)) {
        $slotStart = $current->copy();
        $slotEnd = $current->copy()->addMinutes($totalDuration);

        /*
            بررسی تداخل:
            اگر رزروی وجود داشته باشد که:
            start_time < پایان بازه جدید
            و
            end_time > شروع بازه جدید
            یعنی تداخل دارد.
        */
        $hasConflict = Reservation::where('date', $date)
            ->where(function ($query) use ($slotStart, $slotEnd) {
                $query->where('start_time', '<', $slotEnd->format('H:i:s'))
                      ->where('end_time', '>', $slotStart->format('H:i:s'));
            })
            ->exists();

        if (!$hasConflict) {
            $availableSlots[] = $slotStart->format('H:i');
        }

        // حرکت به اسلات بعدی بر اساس مدت پایه نوبت، مثلاً ۴۵ دقیقه
        $current->addMinutes($slotDuration);
    }

    return response()->json($availableSlots);
}

}
