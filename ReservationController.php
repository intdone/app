<?php

namespace App\Http\Controllers;

use App\Craftsman;
use App\Services;
use App\Absences;
use App\Schedule;
use App\CarMake;
use App\CarModel;
use App\User;
use App\Reservation;
use App\CraftsmanReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DateInterval;
use DateTime;
use DatePeriod;
use DataTables;
use DB;

class ReservationController extends Controller
{


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //მომხარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მიღებული მონაცემები
            $services_id = $request->services_id;
            $craftsman_id = $request->craftsman_id;
            $date = $request->date;
            $car_make_id = $request->car_make_id;
            $car_number = $request->car_number;

        //მარკები
            $carMake = CarMake::orderBy('created_at','DESC')->get();

        //სერვისები
            $services = Services::orderBy('name','ASC')->get();

        //მექანიკოსი
            $craftsman = Craftsman::where(function($query) use ($services_id){

                            if(isset($services_id)){
                                $query->whereJsonContains('services',$services_id);
                            }

                        })->orderBy('name','ASC')->get();




        //მონაცემები
            $data = array(
                'user'     => $user,
                'carMake'     => $carMake,
                'services'     => $services,
                'craftsman'     => $craftsman,
                'services_id'     => $services_id,
                'craftsman_id'     => $craftsman_id,
                'date'     => $date,
                'car_number'     => $car_number,
                'car_make_id'     => $car_make_id,
            );

            return view('reservation.index')->with($data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //მომხარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მარკები
            $carMake = CarMake::orderBy('name','ASC')->get();

        //სერვისები
            $services = Services::orderBy('name','ASC')->get();

        //მონაცემები
            $date = array(
                'user'     => $user,
                'carMake'     => $carMake,
                'services'     => $services,
            );

            return view('reservation.create')->with($date);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        //ვალიდაცია
            $this->validate($request, [
                'car_number' => ['required', 'string'],
                'owner_name' => ['required', 'string'],
                'owner_phone' => ['required', 'string'],
                'car_make_id' => ['required'],
                'car_model_id' => ['required'],
                'services_id' => ['required'],
                'craftsman_id' => ['required'],
                'date' => ['required'],
                'time_start' => ['required']
            ]);

        //მომხმარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მონაცემები
            $car_number = $request->car_number;
            $car_make_id = $request->car_make_id;
            $car_model_id = $request->car_model_id;
            $services_id = $request->services_id;
            $craftsman_id = $request->craftsman_id;
            $date = $request->date;
            $time = $request->time_start;
            $owner_name = $request->owner_name;
            $owner_surname = $request->owner_surname;
            $owner_phone = $request->owner_phone;

        //ჯავნიშვის საჭირო სტატუსები
            $graphics_module = array('სტანდარტული','არასტანდარტული');
        //ხელოსანი
            $craftsman = Craftsman::findOrFail($craftsman_id);
        //სერვისი
            $services = Services::findOrFail($services_id);
        //გრაფიკი
            $schedule = Schedule::where('craftsman_id',$craftsman->id)->where('date',$date)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->first();
        //სერვისის დრო
            $services_time = $services->time;
        //დასაფიქსირებელი დრო განრიგში
            $time_end = $this->timePlus($time,$this->roundUpToAny($services_time));
        //ჩასანაცვლებელი საათების სია
            $time_array = $this->hours_list($time,$time_end);
      
        //რეზერვირებული დროები
            $craftsmanReservation = CraftsmanReservation::where('date',$date)->get();
            $array_craftsman_reservation = array();
            foreach ($craftsmanReservation as $key => $value) {
                $array_craftsman_reservation;
                array_push($array_craftsman_reservation, $this->hours_list($value->time_start,$value->time_end));
            }
            $array_craftsman_reservation = $this->array_flatten($array_craftsman_reservation);
        //სერვისი დასრულების დღე
            $date_end = $this->dateTimePlus($date." ".$time,$services_time);
         
        //რეზერვაციის შენახვა
            $reservation = new Reservation();
            $reservation->user_id=$user_id;
            $reservation->craftsman_id=$craftsman_id;
            $reservation->car_make_id=$car_make_id;
            $reservation->car_model_id=$car_model_id;
            $reservation->services_id=$services_id;
            $reservation->car_number=$car_number;
            $reservation->services_code=$services->code;
            $reservation->date=$date;            
            $reservation->services_time=$services->time;
            $reservation->time_start=$time;
            $reservation->time_message=date("H:i:s", strtotime($time) - 900);//time_schedule
            $reservation->hours_list=implode(",",$time_array);
            $reservation->owner_name=$owner_name;
            $reservation->owner_surname=$owner_surname;
            $reservation->owner_phone=$owner_phone;

        //1 თუ არ არის ორი ან მეტ დღიანი სერვისი
            if($date_end==$date){
                //თუ სერვისის დროის რეინგი არ მოიძებნა ანუ არ არის საკმარისი
                    if(count(array_intersect($array_craftsman_reservation,$time_array))>0)
                        return array('<span class="return-error">აღნიშნული დრო არასაკმარისია სერვისის განსახორციელებლად</span>');
                //შემოწმება რომ სერვისი არ აღემატებოდეს დროს
                    if($time_end>$schedule->working_hours_out)
                        return array('<span class="return-error"> სერვისის დრო აღემატება მექანიკოსის სამუშაო დროს</span>');
                //რეზერვაციის შემწომება
                    $check = Reservation::where('craftsman_id',$craftsman_id)->where('services_id',$services_id)->where('date',$date)->where('time_start',$time)->where('time_end',$time_end)->where('car_number',$car_number)->first();
                    if($check)
                        return array('<span class="return-error">ამ მონაცემებზე უკვე რეზერვირებულია სერვისი</span>');

               //რეზერავიის დაფიქსირება
                    $reservation->time_end=$time_end;
                    $reservation->date_end=$date_end;
                    $reservation->save();

                //რეზერავაციის დაფიქსირება
                    $craftsmanReservation = new CraftsmanReservation();
                    $craftsmanReservation->craftsman_id = $craftsman_id;
                    $craftsmanReservation->schedule_id = $schedule->id;
                    $craftsmanReservation->reservation_id = $reservation->id;
                    $craftsmanReservation->date = $schedule->date;
                    $craftsmanReservation->time_start = $time;
                    $craftsmanReservation->time_end = $time_end;
                    $craftsmanReservation->working_hours_list = implode(",",$this->hours_list($time,$time_end));
                    $craftsmanReservation->save();
                    
            }
        //2 თუ არის ორი ან მეტი დღიანი სერვისი
            elseif($date_end>$date){
                //დღეების რაოდენობის გამოტანა და ერთი დღის მიმატება
                    $date_count = $this->dateDiff($date,$date_end) + 1;                
                // ფუნქციის გამოტანა სანამ არ იქნება სერვისისთვის სული დრო
                    $count_work_time = $this->shedule_for_dayes($craftsman_id,$date,$graphics_module,$date_count);
                //დასაჯავშნი დროის რაოდენობა
                    $services_time_for_sehdule = $this->roundUpToAny($services_time);
                //იქამდე ძებნა სანამ არ მოიძებნება შესაბამისი დრო სერვისის განსახორციელებლად
                    while($count_work_time[0] <= $services_time_for_sehdule){
                        $date_count ++;
                        $count_work_time = $this->shedule_for_dayes($craftsman_id,$date,$graphics_module,$date_count);
                        if($count_work_time == false) return array('<span class="return-error">აღნიშნული დრო არასაკმარისია სერვისის განსახორციელებლად</span>');
                    }
                //დასრულების თარიღი
                    $date_end = $count_work_time[1]->last()->date;

                //რეზერავიის დაფიქსირება
                    $reservation->date_end=$date_end;
                    $reservation->save();

                //გრაფიკში ცვლილებები და მონიშვნა როგორც რეზერვაცია, რეზერვაციის სიმბოლო @
                    $count_hours = 0;
                    $count_hours_reservation = $services_time_for_sehdule / 30;
                    foreach ($count_work_time[1] as $key => $value) {
                        $working_hours_list = $this->hours_list($value->working_hours_in,$value->working_hours_out);
                        $time_start = $value->working_hours_in;
                        if($key==0)$time_start = $time;
                        for ($i=0; $i < count($working_hours_list) ; $i++) { 
                            if($working_hours_list[$i]!='-'){
                                $count_hours++;
                                $time_end = $working_hours_list[$i];
                                if($count_hours==$count_hours_reservation) break;
                            }
                        }
                       
                        //რეზერავაციის დაფიქსირება
                            $craftsmanReservation = new CraftsmanReservation();
                            $craftsmanReservation->craftsman_id = $craftsman_id;
                            $craftsmanReservation->schedule_id = $value->id;
                            $craftsmanReservation->reservation_id = $reservation->id;
                            $craftsmanReservation->date = $value->date;
                            $craftsmanReservation->time_start = $time_start;
                            $craftsmanReservation->time_end = $time_end;
                            $craftsmanReservation->working_hours_list = implode(",",$this->hours_list($value->working_hours_in,$time_end));
                            $craftsmanReservation->save();

                    }

                //რეზერავიის განახლება დროის დასრულებაზე
                    $reservation->time_end=$time_end;
                    $reservation->save();

            }
        //თუ არ მოხდა ჩაწერა
            else{
                return array('<span class="return-error">რეზერვაციია წარუმატებელია, მექანიკოსის არასაკმარისი დროის გამო</span>');
            }

        //წარმატებული დაბრუნება
            return  '
                    <i class="fe-check success-icon"></i>
                    რეზერვაცია წარმატებით დასრულდა
                    <div class="mt-4">
                        <button type="button" onClick="window.location.reload();" class="btn btn-lg rounded-pill btn-primary mx-10 mb-3">ახალი რეზერვაცია</button>
                        <button onclick="redic_reservation()" type="button" class="btn btn-lg rounded-pill btn-dark mx-10 mb-3">რეზერვაციის სია</a>
                    </div>
                ';
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {

        //მომხარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);
        //რეზერვაცია
            $reservation = Reservation::findOrFail($id);
        //გრაფიკი
            $schedule = Schedule::where('craftsman_id',$reservation->craftsman_id)->where('date',$reservation->date)->first();
        //მარკები
            $carMake = CarMake::orderBy('name','ASC')->get();
         //მოდელები
            $carModel = CarModel::where('car_make_id',$reservation->car_make_id)->orderBy('name','DESC')->get();
        //სერვისები
            $services = Services::orderBy('name','ASC')->get();
        //ხელოსნები
            $craftsman = Craftsman::whereJsonContains('services',"$reservation->services_id")->orderBy('name','ASC')->get();
        //ჯავნიშვის საჭირო სტატუსები
            $graphics_module = array('სტანდარტული','არასტანდარტული');
        //თავისუფალი დღეები
            $schedule_array = Schedule::where('craftsman_id',$reservation->craftsman_id)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->pluck('date')->toArray();

        //მონაცემები
            $date = array(
                'user'     => $user,
                'reservation'     => $reservation,
                'schedule'     => $schedule,
                'carMake'     => $carMake,
                'services'     => $services,
                'craftsman'     => $craftsman,
                'carModel'     => $carModel,
                'schedule_array'     => $schedule_array,
            );

            return view('reservation.edit')->with($date);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        //ვალიდაცია
            $this->validate($request, [
                'car_number' => ['required', 'string'],
                'owner_name' => ['required', 'string'],
                'owner_phone' => ['required', 'string'],
                'car_make_id' => ['required'],
                'car_model_id' => ['required'],
                'services_id' => ['required'],
                'craftsman_id' => ['required'],
            ]);

        //მომხმარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მონაცემები
            $car_number = $request->car_number;
            $car_make_id = $request->car_make_id;
            $car_model_id = $request->car_model_id;
            $services_id = $request->services_id;
            $craftsman_id = $request->craftsman_id;
            $date = $request->date;
            $time = $request->time_start;
            $owner_name = $request->owner_name;
            $owner_surname = $request->owner_surname;
            $owner_phone = $request->owner_phone;

        //რეზერვაცია
            $reservation = Reservation::findOrFail($id);
        //თუ დრო არ მოივა
            if(empty($time)) $time=$reservation->time_start;
        //ჯავნიშვის საჭირო სტატუსები
            $graphics_module = array('სტანდარტული','არასტანდარტული');
        //ხელოსანი
            $craftsman = Craftsman::findOrFail($craftsman_id);
        //სერვისი
            $services = Services::findOrFail($services_id);
        //გრაფიკი
            $schedule = Schedule::where('craftsman_id',$craftsman->id)->where('date',$date)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->first();
        //სერვისის დრო
            $services_time = $services->time;
        //დასაფიქსირებელი დრო განრიგში
            $time_end = $this->timePlus($time,$this->roundUpToAny($services_time));
        //ჩასანაცვლებელი საათების სია
            $time_array = $this->hours_list($time,$time_end);
      
        //რეზერვირებული დროები
            $craftsmanReservation = CraftsmanReservation::where('date',$date)->get();
            $array_craftsman_reservation = array();
            foreach ($craftsmanReservation as $key => $value) {
                $array_craftsman_reservation;
                array_push($array_craftsman_reservation, $this->hours_list($value->time_start,$value->time_end));
            }
            $array_craftsman_reservation = $this->array_flatten($array_craftsman_reservation);
        //სერვისი დასრულების დღე
            $date_end = $this->dateTimePlus($date." ".$time,$services_time);

        //რეზერვაციის განახლება
            $reservation->craftsman_id=$craftsman_id;
            $reservation->car_make_id=$car_make_id;
            $reservation->car_model_id=$car_model_id;
            $reservation->services_id=$services_id;
            $reservation->car_number=$car_number;
            $reservation->owner_name=$owner_name;
            $reservation->owner_surname=$owner_surname;
            $reservation->owner_phone=$owner_phone;
            $reservation->save();

        //1 ერთ დღიანი სერვისის რედაქტირების დროს
            if($date_end==$date){
                //თუ მოვიდა ახალი დრო
                    if($request->time_start){
                        //რეზერვაციის შემწომება
                            $check = Reservation::where('craftsman_id',$craftsman_id)->where('services_id',$services_id)->where('date',$date)->where('time_start',$request->time_start)->where('time_end',$time_end)->where('car_number',$car_number)->first();
                            if($check)
                                return array('<span class="return-error">ამ მონაცემებზე  რეზერვირებულია უკვე რედაქტირებულია</span>');

                        //თუ სერვისის დროის რეინგი არ მოიძებნა ანუ არ არის საკმარისი
                            if(count(array_intersect($array_craftsman_reservation,$time_array))>0)
                                return array('<span class="return-error">აღნიშნული დრო არასაკმარისია სერვისის განსახორციელებლად</span>');
                        //შემოწმება რომ სერვისი არ აღემატებოდეს დროს
                            if($time_end>$schedule->working_hours_out)
                                return array('<span class="return-error"> სერვისის დრო აღემატება მექანიკოსის სამუშაო დროს</span>');

                        //რეზერვაციის შენახვა
                            $reservation->services_code=$services->code;
                            $reservation->services_time=$services->time;
                            $reservation->date=$date;
                            $reservation->date_end=$date_end;
                            $reservation->time_start=$time;
                            $reservation->time_end=$time_end;
                            $reservation->time_message=date("H:i:s", strtotime($time) - 900);
                            $reservation->hours_list=implode(",",$time_array);
                            $reservation->save();

                        //რეზერვირებული გრაფიკის განახლება
                            $craftsmanReservation = CraftsmanReservation::where('reservation_id',$reservation->id)->first();
                            $craftsmanReservation->craftsman_id = $craftsman_id;
                            $craftsmanReservation->schedule_id = $schedule->id;
                            $craftsmanReservation->date = $date;
                            $craftsmanReservation->time_start = $time;
                            $craftsmanReservation->time_end = $time_end;
                            $craftsmanReservation->working_hours_list = implode(",",$this->hours_list($time,$time_end));
                            $craftsmanReservation->save();
                    }
                }

        //2 ორი და ორზე მეტი დღინი სერვისი დერაქტირების დროს
            elseif($date_end>$date){
                //დღეების რაოდენობის გამოტანა და ერთი დღის მიმატება
                    $date_count = $this->dateDiff($date,$date_end) + 1;                
                // ფუნქციის გამოტანა სანამ არ იქნება სერვისისთვის სული დრო
                    $count_work_time = $this->shedule_for_dayes($craftsman_id,$date,$graphics_module,$date_count);
                //დასაჯავშნი დროის რაოდენობა
                    $services_time_for_sehdule = $this->roundUpToAny($services_time);
                

                //თუ მოვიდა ახალი დრო
                    if($request->time_start){
                        //იქამდე ძებნა სანამ არ მოიძებნება შესაბამისი დრო სერვისის განსახორციელებლად
                            while($count_work_time[0] <= $services_time_for_sehdule){
                                $date_count ++;
                                $count_work_time = $this->shedule_for_dayes($craftsman_id,$date,$graphics_module,$date_count);
                                if($count_work_time == false) return array('<span class="return-error">აღნიშნული დრო არასაკმარისია სერვისის განსახორციელებლად</span>');
                            }
                        //დასრულების თარიღი
                            $date_end = $count_work_time[1]->last()->date;

                        //რეზერავიის დაფიქსირება
                            $reservation->date_end=$date_end;

                            $reservation->services_code=$services->code;
                            $reservation->services_time=$services->time;
                            $reservation->date=$date;
                            $reservation->date_end=$date_end;
                            $reservation->time_start=$time;
                            $reservation->time_message=date("H:i:s", strtotime($time) - 900);
                            $reservation->hours_list=implode(",",$time_array);
                            $reservation->save();

                        //ძველი გრაფიკული რეზერვაციების წაშლა
                            CraftsmanReservation::where('reservation_id',$reservation->id)->delete();

                        //გრაფიკში ცვლილებები და მონიშვნა როგორც რეზერვაცია, რეზერვაციის სიმბოლო @
                            $count_hours = 0;
                            $count_hours_reservation = $services_time_for_sehdule / 30;
                            foreach ($count_work_time[1] as $key => $value) {
                                $time_start = $value->working_hours_in;
                                if($key==0)$time_start = $time;
                                $working_hours_list = $this->hours_list($value->working_hours_in,$value->working_hours_out);
                                for ($i=0; $i < count($working_hours_list) ; $i++) { 
                                    if($working_hours_list[$i]!='-'){
                                        $count_hours++;
                                        $time_end = $working_hours_list[$i];
                                        if($count_hours==$count_hours_reservation) break;
                                    }
                                }
                               
                                //რეზერავაციის დაფიქსირება
                                    $craftsmanReservation = new CraftsmanReservation();
                                    $craftsmanReservation->craftsman_id = $craftsman_id;
                                    $craftsmanReservation->schedule_id = $value->id;
                                    $craftsmanReservation->reservation_id = $reservation->id;
                                    $craftsmanReservation->date = $value->date;
                                    $craftsmanReservation->time_start = $time_start;
                                    $craftsmanReservation->time_end = $time_end;
                                    $craftsmanReservation->working_hours_list = implode(",",$this->hours_list($value->working_hours_in,$time_end));
                                    $craftsmanReservation->save();

                            }

                        //რეზერავიის განახლება დროის დასრულებაზე
                            $reservation->time_end=$time_end;
                            $reservation->save();
                    }

            }

        //თუ შეინახა ვანახლებთ გრაფიკსაც
            if($reservation){

                //დაბრუნება
                    return  '
                                <i class="fe-check success-icon"></i>
                                რეზერვაცია წარმატებით დარედაქტირდა
                                <div class="mt-4">
                                    <button type="button" onClick="window.location.reload();" class="btn btn-lg rounded-pill btn-primary mx-10 mb-3">ახალი რეზერვაცია</button>
                                </div>
                            ';
            }
        //თუ არ მოხდა რედაქტირება
            else{
                return array('<span class="return-error">წარუმატებელი რედაქტირება</span>');
            }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {

        //ვალიდაცია
            $this->validate($request, [
                'reservation_id' => ['required'],
                'destroy_status' => ['required', 'string'],
                'destroy_color' => ['required', 'string'],
            ]);

        //მომხმარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მონაცემები
            $reservation_id = $request->reservation_id;
            $destroy_status = $request->destroy_status;
            $destroy_color = $request->destroy_color;
            $check_message = 0;

        //რეზერვაცია
            $reservation = Reservation::findOrFail($reservation_id);

        //რეზერვაციის განახლება
            $reservation->destroy_status=$destroy_status;
            $reservation->destroy_color=$destroy_color;
            $reservation->check_message=$check_message;
            $reservation->save();

        //გრაფიკიდან ამოღება თუ გაუქმდნა
            $array = array('გაუქმება მფლობელის მოთხოვნით','გაუქმება მექანიკოსის მიზეზით','არ გამოცხადდა');
            if(in_array($destroy_status,$array)){
                $craftsmanReservation = CraftsmanReservation::where('reservation_id',$reservation->id)->delete();    
            }
     
        //დაბრუნება
            return  '<i class="fe-check success-icon"></i>რეზერვაცია წარმატებით გაუქმდა';
    }

    public function list(Request $request)
    {
        //ხედიდან გაგზავნილი პარამეტრის ან ცვლადის გამოსაყენებლად
        $params = $request->params;
        $services_id = $request->services_id;
        $craftsman_id = $request->craftsman_id;
        $date =$request->date;
        $car_make_id = $request->car_make_id;
        $car_number = $request->car_number;

        if($date){
            $array = explode('/', $date);
            $date = $array[2].'-'.$array[1].'-'.$array[0];
        }




        $start = date("Y-m-d", strtotime($request['start']));
        $end = date("Y-m-d", strtotime($request['end']));

        $query = Reservation::where(function($query) use ($start,$end,$services_id,$craftsman_id,$date,$car_make_id,$car_number){


                            if(isset($services_id)){
                                $query->where('services_id',$services_id);
                            }

                            if(isset($craftsman_id)){

                                $query->where('craftsman_id',$craftsman_id);
                            }

                            if(isset($car_make_id)){
                                $car_make_id = explode(",", $car_make_id);
                                $query->whereIn('car_make_id',$car_make_id);
                            }

                            if(isset($date)){
                                $query->where('date',$date);
                            }
                            else{
                                if(isset($start)){
                                    $query->where('date','>=',$start);
                                }

                                if(isset($end)){
                                    $query->where('date','<=',$end);
                                }

                            }
                            if(isset($car_number)){
                                $query->where('car_number','LIKE','%'.$car_number.'%');
                            }


                        })->with('craftsman')->with('services')->with('car_make')->with('car_model')->get();

        $events = [];

        foreach ($query as $key => $value) {
            $className = $value->destroy_color;
            //თU არის ცივი სერვისი
            if($value->services->cold==1 && $value->destroy_status=="მიმდინარე") $className="bg-orange-cold";
            $events[] = [
                'car_number' => $value->car_number,
                'date' => $value->date,
                'time_start' => $value->time_start,
                'owner_name' => $value->owner_name,
                'owner_surname' => $value->owner_surname,
                'owner_phone' => $value->owner_phone,
                'craftsman_name' => $value->craftsman->name." ".$value->craftsman->surname,
                'craftsman_phone' => $value->craftsman->phone,
                'car_make' => $value->car_make->name,
                'car_model' => $value->car_model->name,
                'services' => $value->services->name,
                'services_code' => $value->services->code,
                'services_time' => $value->services_time,
                'reservation_date' => $value->date,
                'reservation_time' => $value->time_start,
                'reservation_id' => $value->id,
                'reservation_destroy_status' => $value->destroy_status,
                'id' => $value->id,
                'title' => $value['car_number']." - ".$value->services->name." - ".$value->craftsman->name." ".$value->craftsman->surname,
                'start' => $value->date."T".$value->time_start,
                'end' => $value->date_end."T".$value->time_end,
                'className' => $className
            ];
        }


        return $events;
    }

    public function hours_list($in, $out, $status=0){
        
        //ცარიელი, დროის იტერვალი  დროები და შემოსული დროის სტრინგის გადატანა
            $array_hour = array();

            for( $i=strtotime($in); $i<=strtotime($out); $i+=1800){
                if($status==1){
                    array_push($array_hour, date("H:i:s",$i));
                }
                else{
                    array_push($array_hour, date("H:i",$i));    
                }
                
            }
            
        //დააბრუნებს საათების მასივს
            return  $array_hour;
    }

    public function roundUpToAny($n,$x=30) {
        return round(($n+$x/2)/$x)*$x;
    }

    public function timePlus($hours,$time){

        $minutes_to_add = $time;

        $time = new DateTime($hours);
        $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));

        $stamp = $time->format('H:i');

        return $stamp;
    }

    public function dateTimePlus($dateTime,$time){

        $minutes_to_add = $time;

        $time = new DateTime($dateTime);
        $time->add(new DateInterval('PT' . $minutes_to_add . 'M'));

        $stamp = $time->format('Y-m-d');// H:i

        return $stamp;
    }

    public function timeDiff($start,$end){

        $diff = abs(strtotime($start) - strtotime($end));
        $tmins = $diff/60;
        $hours = floor($tmins/60);
        $mins = $tmins%60;
        return $tmins;
    }

    public function dateDiff($start,$end){
        $start=date_create($start);
        $end=date_create($end);
        $diff=date_diff($start,$end);
        return (int)$diff->format("%a");
    }

    public function model(Request $request, $id){

        //მოდელები
            $carModel = CarModel::where('car_make_id',$id)->orderBy('name','DESC')->get()->toArray();

        //დაბრუნება
            return $carModel;
    }

    public function craftsman(Request $request, $id){

        //ხელოსნები
            $craftsman = Craftsman::whereJsonContains('services',$id)->orderBy('name','ASC')->get()->toArray();

        //დაბრუნება
            return $craftsman;
    }

    public function schedule(Request $request, $id){

        //ჯავნიშვის საჭირო სტატუსები
            $graphics_module = array('სტანდარტული','არასტანდარტული');
        //თავისუფალი დღეები
            $schedule = Schedule::where('craftsman_id',$id)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->pluck('date')->toArray();
        //დაბრუნება
            return $schedule;
    }

    public function hours(Request $request, $id){

        //ჯავნიშვის საჭირო სტატუსები
            $graphics_module = array('სტანდარტული','არასტანდარტული');
        //თავისუფალი დღეები
            $schedule = Schedule::where('craftsman_id',$id)->where('date',$request->date)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->first();
        //საათების სტრინგი
            $hours = $this->hours_list($schedule->working_hours_in,$schedule->working_hours_out);

        //რეზერვაციების გამოჩენა
            for ($i=0; $i < count($hours) ; $i++) { 
                $arr = array();
                $craftsmanReservation = CraftsmanReservation::where('craftsman_id',$id)->where('date',$request->date)->where('time_start','<=',$hours[$i])->where('time_end','>=',$hours[$i])->get();
                if(count($craftsmanReservation)>0){
                    foreach ($craftsmanReservation as $key => $value) {
                        array_push($arr,$hours[$i]." ".$value->reservation->car_number);
                    }
                }
                else{
                    array_push($arr,$hours[$i]);
                }
                
                $hours[$i]=$arr;
            }

        //დაბრუნება
            return $hours;
    }

    public function array_flatten($array) { 
      if (!is_array($array)) { 
        return FALSE; 
      } 
      $result = array(); 
      foreach ($array as $key => $value) { 
        if (is_array($value)) { 
          $result = array_merge($result, $this->array_flatten($value)); 
        } 
        else { 
          $result[$key] = $value; 
        } 
      } 
      return array_values(array_unique($result)); 
    } 

    public function shedule_for_dayes($craftsman_id,$date,$graphics_module,$date_count){

        //თავისუფალი დღეების გამოტანა თანმიმდევრობით
            $free_date_schedule = Schedule::where('craftsman_id',$craftsman_id)->where('date','>=',$date)->whereIn('graphics_module',$graphics_module)->orderBy('date','ASC')->take($date_count)->get();

        //შემოწმება რომ ხელოსანს ჰქონდეს საკმარისი თავისუფალი დღეს დიდი სერვისის განსახორიელებლად, და რომ არ მოხდეც ჩაციკვლა ძებნის დროს
            if(count($free_date_schedule)!=$date_count) return false;

        //საათების მასივი დასაწყისი და დასასრული
            $date_arrays_in  = $free_date_schedule->pluck('working_hours_in');
            $date_arrays_out  = $free_date_schedule->pluck('working_hours_out');
        //ხელოსნის სრული სამუშაო დრო (დღეების მითიტებით)
            $count_work_time = 0;
            for ($i=0; $i < count($date_arrays_in) ; $i++) { 
                $count_work_time += $this->timeDiff($date_arrays_in[$i],$date_arrays_out[$i]);
            }
        //მთლიანი დროის გამოტანა
            return array($count_work_time, $free_date_schedule);
    }

    public function message(){

        //დღევანდელი დღე
            $date  = date('Y-m-d');
            $time = date('H:i:s');

        //რეზეწრვაცია შეტყობინებითვის
            $reservation_messages = Reservation::where('destroy_status','მიმდინარე')->where('date',$date)->where('check_message',1)->get();

        //მონაცემები
            $data = array(
                'reservation_messages' => $reservation_messages,
                'time' => $time,
            );

            return view('calendar.messages')->with($data);
    }

    public function message_remove($id){

        $reservation = Reservation::findOrFail($id);
        $reservation->check_message=0;
        $reservation->save();

        return true;
    }
}
