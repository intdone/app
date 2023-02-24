<?php

namespace App\Http\Controllers;

use App\Craftsman;
use App\Absences;
use App\Schedule;
use App\User;
use App\CraftsmanReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DataTables;
use DB;

class AbsencesController extends Controller
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
    public function index()
    {
        //მომხარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

         //ხელოსნები
            $craftsman = Craftsman::get();

        //მონაცემები
            $date = array(
                'user'     => $user,
                'craftsman'     => $craftsman,
            );

            return view('absences.index')->with($date);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
                'craftsman_id' => ['required'],
                'date_start' => ['required', 'string'],
                'date_end' => ['required', 'string'],
                'status' => ['required', 'string']
            ]);

        //მომხმარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მონაცემები
            $craftsman_id = $request->craftsman_id;
            $date_start = $request->date_start;
            $date_end = $request->date_end;
            $status = $request->status;

        //ხლოსანი
            $craftsman = Craftsman::find($craftsman_id);

        //თუ არის უკვე რეგისტრირებულია
            $check = Absences::where('craftsman_id',$craftsman_id)->where('date_start','>=',$request->date_start)->where('date_end','<=',$request->date_end)->count();

        //შემოწმება თუ აირჩია უკვე მსგავსიტარიღები
            if($check>0)  
                return array('<span class="return-error">'.$request->date_start.' '.$request->date_end.' ამ დროებზე უკვე შექმნილია გაცდენის გრაფიკი</span>');

            if($check==0){
                //გაცდენის დაფიქსირება
                $absences = new Absences();
                $absences->user_id=$user->id;
                $absences->craftsman_id=$craftsman_id;
                $absences->date_start=$request->date_start;
                $absences->date_end=$request->date_end;
                $absences->status=$status;
                $absences->save();
            }

        //თარიღები
            $start_date = $this->dateMinus($date_start,1);
            $end_date = $this->dateMinus($date_end,1);

        //თარიღები
            while (strtotime($start_date) <= strtotime($end_date)) {

                //დღის მომატება ერთი დღით
                    $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
                //კვირის სახელი
                    $workdays = date('D', strtotime($start_date));
                //თუ კვირის დღე არის გაატაროს
                    if(in_array($workdays, array('Sun'))) continue;
                //საათების სტრინგი
                    $working_hours_list =  $this->working_hours_list($workdays,null,null);
                //ტიპი
                    $graphics_module = 'რეგისტრირებული გაცდენა';
                //ფერი
                    $color = 'absence';
                //თუ არის სამუშაო განრგიში
                    $schedule = Schedule::where('craftsman_id',$craftsman_id)->where('date',$start_date)->first();

                //შემოწმება ჯავშანზე
                    if($schedule){
                        $craftsmanReservation = CraftsmanReservation::where('date',$schedule->date)->get();
                        if(count($craftsmanReservation)>0) continue;
                    }

                //თუ არ არის ამუშაო განრიგში
                    if($schedule){
                        $schedule->graphics_module=$graphics_module;
                        $schedule->color=$color;
                        $schedule->working_hours_list=$working_hours_list;
                        $schedule->save();
                    }
                    else{
                        $schedule = new Schedule();
                        $schedule->graphics_module=$graphics_module;
                        $schedule->workdays=$workdays;
                        $schedule->absences_id=$absences->id;
                        $schedule->user_id=$user_id;
                        $schedule->craftsman_id=$craftsman_id;
                        $schedule->date=$start_date;
                        $schedule->color=$color;
                        $schedule->working_hours_list=$working_hours_list;
                        $schedule->save();
                    }
                    

            }

        
        
       
        //დაბრუნება
            return '<i class="fe-check"></i> გაცდენა წარმატებით დარეგისტრირდა';

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
        //ხელოსანი
            $craftsman     = Craftsman::findOrFail($id);

        //თარიღები
            $date_start = $request->date_start;
            $date_end = $request->date_end;

        //რეგისტრირებული გაცდენა
            $absences = Absences::where('craftsman_id',$id)->where('date_start',$date_start)->where('date_end',$date_end )->first();
          
        //მონაცემები
            $date = array(
                'craftsman'     => $craftsman,
                'absences'     => $absences,
                'date_start'     => $date_start,
                'date_end'     => $date_end,
            );

            return view('absences.show')->with($date);
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
                'date_in' => ['required'],
                'date_out' => ['required'],
                'status' => ['required']
            ]);

        //მომხმარებელი
            $user_id = auth()->user()->id;
            $user     = User::findOrFail($user_id);

        //მონაცემები
            $date_in=$request->date_in;
            $date_out=$request->date_out;
            $status=$request->status;

        //თარიღები
            $start_date = $this->dateMinus($date_in,1);
            $end_date = $this->dateMinus($date_out,1);

        //გაცდენის გრაფიკი
            $absences = Absences::findOrFail($id);
            $absences->date_start=$date_in;
            $absences->date_end=$date_out;
            $absences->status=$status;
            $absences->save();

        //ძველი მონაცემების განახლება
            $schedule = Schedule::where('absences_id',$absences->id)->get();
            foreach ($schedule as $key => $value) {

                //შემოწმება ჯავშანზე
                    $craftsmanReservation = CraftsmanReservation::where('date',$value->date)->get();
                    if(count($craftsmanReservation)>0) continue;

                //საათების სტრინგი
                    $working_hours_list =  $this->working_hours_list($value->workdays,null,null);
                //ტიპი
                    $graphics_module = 'დღის ჭრილი';
                //ფერი
                    $color = 'space';
                //შენავა გაუქმებაზე
                    $value->graphics_module=$graphics_module;
                    $value->color=$color;
                    $value->working_hours_list=$working_hours_list;
                    $value->save();
            }
        //ახალი მონაცემები
            //თარიღები
            while (strtotime($start_date) <= strtotime($end_date)) {

                //დღის მომატება ერთი დღით
                    $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
                //კვირის სახელი
                    $workdays = date('D', strtotime($start_date));
                //თუ კვირის დღე არის გაატაროს
                    if(in_array($workdays, array('Sun'))) continue;
                //საათების სტრინგი
                    $working_hours_list =  $this->working_hours_list($workdays,null,null);
                //ტიპი
                    $graphics_module = 'რეგისტრირებული გაცდენა';
                //ფერი
                    $color = 'absence';

                //თუ უკვე არის თავისუფალი გადააწეროს თავისუფალ გრაფის
                    $check = Schedule::where('craftsman_id',$absences->craftsman_id)->where('date',$start_date)->first();

                //შემოწმება ჯავშანზე
                    $craftsmanReservation = CraftsmanReservation::where('date',$check->date)->get();
                    if(count($craftsmanReservation)>0) continue;
                    
                    if($check){
                        $schedule=Schedule::find($check->id);
                        $schedule->graphics_module=$graphics_module;
                        $schedule->workdays=$workdays;
                        $schedule->absences_id=$absences->id;
                        $schedule->user_id=$user_id;
                        $schedule->craftsman_id=$absences->craftsman_id;
                        $schedule->date=$start_date;
                        $schedule->color=$color;
                        $schedule->working_hours_list=$working_hours_list;
                        $schedule->save();
                    }
                //მონაჩეების ჩაწერა
                    else{
                        $schedule = new Schedule();
                        $schedule->graphics_module=$graphics_module;
                        $schedule->workdays=$workdays;
                        $schedule->absences_id=$absences->id;
                        $schedule->user_id=$user_id;
                        $schedule->craftsman_id=$absences->craftsman_id;
                        $schedule->date=$start_date;
                        $schedule->color=$color;
                        $schedule->working_hours_list=$working_hours_list;
                        $schedule->save();
                    }
                
            }


        return '<i class="fe-check"></i> წარმატებით დარედაქტირდა';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Schedule  $schedule
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {   

        //ძველი ჩანაწერების განახლება
            $absences = Absences::findOrFail($id);
        //გრაფიკი
            $schedule = Schedule::where('craftsman_id',$absences->craftsman_id)->where('date','>=',$absences->date_start)->where('date','<=',$absences->date_end)->where('graphics_module','რეგისტრირებული გაცდენა')->get();
            foreach ($schedule as $key => $value) {

                //საათების სტრინგი
                    $working_hours_list =  $this->working_hours_list($value->workdays,null,null);
                //ტიპი
                    $graphics_module = 'დღის ჭრილი';
                //ფერი
                    $color = 'space';
                //შენავა გაუქმებაზე
                    $value->graphics_module=$graphics_module;
                    $value->color=$color;
                    $value->working_hours_list=$working_hours_list;
                    $value->save();
            }
        //გაცდენის წაშლა
            $absences->delete();
        
        //დაბრუნება
            return '<i class="fe-check"></i> წარმატებით წაიშალა';

    }


    public function list(Request $request)
    {
        //ხედიდან გაგზავნილი პარამეტრის ან ცვლადის გამოსაყენებლად
        $params = $request->params;
     
        $whereClause = $params['sac'];
     
        $query = Absences::with('craftsman');

        return DataTables::eloquent($query)->toJson();

    }


    public function datePlus($date,$number){

        $date = date('Y-m-d', strtotime($date . ' +'.$number.' day'));
        return $date;
    }

    public function dateMinus($date,$number){

        $date = date('Y-m-d', strtotime($date . ' -'.$number.' day'));
        return $date;
    }

    public function working_hours_list($workdays, $in, $out){
        
        //ცარიელი, დროის იტერვალი  დროები და შემოსული დროის სტრინგის გადატანა
            $array_empty = array();
            $array_hour = array();
            $array_schedules = array();

            $HOUR_IN = env('HOUR_IN');
            $HOUR_OUT = env('HOUR_OUT');

            //თუ იყო შაბათი
            if($workdays=="Sat") $HOUR_OUT = env('HOUR_OUT_SA');

            for( $i=strtotime($HOUR_IN); $i<=strtotime($HOUR_OUT); $i+=1800){
                array_push($array_empty, '-');
            }

            for( $i=strtotime($HOUR_IN); $i<=strtotime($HOUR_OUT); $i+=1800){
                array_push($array_hour, date("H:i",$i));
            }
            
            for( $i=strtotime($in); $i<=strtotime($out); $i+=1800){
                array_push($array_schedules, date("H:i",$i));
            }
            
            //საათების სტრინგი
                $working_hours_list = implode(",",array_replace($array_empty, array_intersect($array_hour,$array_schedules)));

        //დააბრუნებს საათების მასივს
            return  $working_hours_list;
    }



}
