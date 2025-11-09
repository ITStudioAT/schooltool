<?php

namespace App\Services;

use App\Models\Register;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\SimpleExcel\SimpleExcelWriter;

class PrintRegisterService
{

    public function printSupervisor($user, $data)
    {
        $register = Register::findOrFail($data['register_id']);
        $filename = Str::slug($register->name, '_') . '_' . now()->format('Ymd_His') . '_betreuer.pdf';

        $path = storage_path('app/private/pdf/' . $filename);

        $bookings = $register->bookings()
            ->with([
                'user:id,last_name,first_name,email,phone',
                'registerDate:id,register_id,date,from,to,supervisor',
            ])
            ->join('register_dates as rd', 'register_date_bookings.register_date_id', '=', 'rd.id')
            ->join('users', 'register_date_bookings.user_id', '=', 'users.id')
            ->orderBy('rd.supervisor')
            ->orderBy('rd.date')
            ->orderBy('rd.from')
            ->orderBy('users.last_name')
            ->select('register_date_bookings.*')
            ->get();

        $totalsBySupervisor = $bookings
            ->groupBy(fn($b) => $b->registerDate->supervisor ?? '')
            ->map->count()
            ->toArray();

        $totalCount = $bookings->count();


        $data = [
            'register_name' => $register->name,
            'bookings' => $bookings->toArray(),
            'totals_by_supervisor' => $totalsBySupervisor,
            'total_count'         => $totalCount,
        ];

        // info($data['bookings']);



        Pdf::view('pdfs.registerSupervisor', ['data' => $data])
            ->format(Format::A4)
            ->headerView('pdfs.registerSupervisor_header', ['title' => $data['register_name']])
            ->footerView('pdfs.registerSupervisor_footer')
            ->save($path);
        return $path;
    }

    public function printExcel($user, $data)
    {

        $register = Register::findOrFail($data['register_id']);
        $filename = Str::slug($register->name, '_') . '_' . now()->format('Ymd_His') . '.xlsx';

        $path = storage_path('app/private/excel/' . $filename);

        $bookings = $register->bookings()
            ->with([
                'user:id,last_name,first_name,email,phone',
                'registerDate:id,register_id,date,from,to,supervisor',
            ])
            ->join('register_dates as rd', 'register_date_bookings.register_date_id', '=', 'rd.id')
            ->join('users', 'register_date_bookings.user_id', '=', 'users.id')
            ->orderBy('rd.date')
            ->orderBy('rd.from')
            ->orderBy('rd.supervisor')
            ->orderBy('users.last_name')
            ->select('register_date_bookings.*') // keep main table clean
            ->get();



        $excel = SimpleExcelWriter::create($path)
            ->addHeader(
                [
                    'Datum',
                    'Von',
                    'Bis',
                    'Betreuer',
                    'Kind N.n.',
                    'Kind V.n.',
                    'Geb-Datum',
                    'Nachname',
                    'Vorname',
                    'Email',
                    'Telefon'
                ]
            );


        foreach ($bookings as $booking) {
            $excel->addRow([
                'Datum' => $booking->registerDate->date,
                'Von'   => $booking->registerDate->from,
                'Bis'   => $booking->registerDate->to,
                'Betreuer' => $booking->registerDate->supervisor,
                'Kind N.n.'   => $booking->student_last_name,
                'Kind V.n.'   => $booking->student_first_name,
                'Geb-Datum'   => $booking->student_birthdate,
                'Nachname'   => $booking->user->last_name,
                'Vorname'    => $booking->user->first_name,
                'Email'      => $booking->user->email,
                'Telefon'    => $booking->user->phone,
            ]);
        }

        return $path;
    }
}
