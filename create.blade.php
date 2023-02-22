@extends('layouts.app')

@section('content')
   
<section class="boxes-page">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="sec-title">
                    <h2>{{__('რეზერვაცია')}}</h2>
                    <h4><i class="fe-check"></i>{{__('ავტომობილის სერვისზე ჩაწერა')}}</h4>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body">
                <form action="{!! action('ReservationController@store') !!}" method="post" id="form-store" autocomplete="off">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label>{{__('ავტომობილის სახ. ნომერი')}}</label>
                                    <input type="text" class="form-control" name="car_number" required placeholder="{{__('ავტომობილის სახ. ნომერი')}}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>{{__('ავტომობილის მარკა')}}</label>
                                    @if(count($carMake)>0)
                                        <select class="form-control select2-multiple" data-toggle="select2" name="car_make_id" id="car_make_id"  onchange="model()" data-width="100%"  data-placeholder="{{ __('ავტომობილის მარკა') }}">
                                            <option value="">{{__('აირჩიეთ')}}</option>
                                            @foreach($carMake as $item)
                                                <option value="{{$item->id}}">{{$item->name}}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>{{__('ავტომობილის მოდელი')}}</label>
                                    <select class="form-control car_model" data-toggle="select2" name="car_model_id" id="car_model_id" data-width="100%">
                                        <option value="">{{__('აირჩიეთ')}}</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>{{__('სერვისი / სერვისის კოდი')}}</label>
                                    @if(count($services)>0)
                                        <select class="form-control" data-toggle="select2" name="services_id" id="services_id" onchange="craftsman()" data-width="100%">
                                            <option value="">{{__('აირჩიეთ')}}</option>
                                            @foreach($services as $item)
                                                <option value="{{$item->id}}">{{$item->name}}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>{{__('მექანიკოსი')}}</label>
                                    <select class="form-control" data-toggle="select2" name="craftsman_id" onchange="schedule()" id="craftsman_id" data-width="100%">
                                        <option value="">{{__('აირჩიეთ')}}</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label>{{__('თარიღი')}}</label>
                                    <input type="text" class="form-control date-icon" id="date" onchange="hours()"  name="date" placeholder="{{__('თარიღი')}}">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label>{{__('დრო')}}</label>
                                    <select class="form-control" data-toggle="select2" name="time_start"  id="time_start" data-width="100%">
                                    </select>
                                </div>
                                
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label>{{__('ავტომობილის მფლობელის სახელი')}}</label>
                                    <input type="text" class="form-control" name="owner_name" placeholder="{{__('ავტომობილის მფლობელის სახელი')}}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>{{__('ავტომობილის მფლობელის გვარი')}}</label>
                                    <input type="text" class="form-control" name="owner_surname" placeholder="{{__('ავტომობილის მფლობელის გვარი')}}">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>{{__('ავტომობილის მფლობელის ნომერი')}}</label>
                                    <input type="tel" class="form-control" name="owner_phone" placeholder="{{__('ავტომობილის მფლობელის ნომერი')}}">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <button type="button"  onclick="store(1)"  class="btn btn-lg float-end rounded-pill btn-primary">{{__('რეგისტრაცია')}}</button>
                                </div>  
                            </div>

                            <div class="row align-items-center schedule-bottom">
                                <div class="col-lg-12 text-center">
                                    <h4 id="previewSave"></h4>
                                </div>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row align-items-center schedule-bottom">
            <div class="col-lg-4">
                <a href="{{url('reservation')}}"><i class="fe-chevron-left"></i>{{ __('რეზერვაციის სია') }}</a>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
    <script src="{{asset('js/moment.js')}}"></script>

	<script type="text/javascript">

        //მოდელები მარკის მიხედვით
            function model(){
                var make_id = $('#car_make_id').val(), model_id = document.getElementById('car_model_id');
                $("#car_model_id").empty();
                $.ajaxSetup({ headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") } });
                $.ajax({
                    type: "GET",
                    url: '{{url('reservation-car-model')}}/'+make_id+'',
                    data: { },
                    success: function (e) {
                        if(e.length){
                            var opt = document.createElement('option');
                            opt.value = "";
                            opt.innerHTML = "{{__('აირჩიეთ')}}";
                            model_id.appendChild(opt);


                            for(var i=0; i<e.length; i++){
                                var opt = document.createElement('option');
                                opt.value = e[i]['id'];
                                opt.innerHTML = e[i]['name'];
                                model_id.appendChild(opt);
                            }
                        }
                    },
                    error: function (e) {
                        console.log("Error:", e);
                    },
                });

            }

        //ხელოსანი
            function craftsman(){
                $("#date").datepicker("destroy");
                $("#time_start").empty();
                $("#date").val('');
                
                var services_id = $('#services_id').val(), craftsman_id = document.getElementById('craftsman_id');
                $("#craftsman_id").empty();
                $.ajaxSetup({ headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") } });
                $.ajax({
                    type: "GET",
                    url: '{{url('reservation-craftsman')}}/'+services_id+'',
                    data: { },
                    success: function (e) {
                        if(e.length){
                            var opt = document.createElement('option');
                            opt.value = "";
                            opt.innerHTML = "{{__('აირჩიეთ')}}";
                            craftsman_id.appendChild(opt);

                            for(var i=0; i<e.length; i++){
                                var opt = document.createElement('option');
                                opt.value = e[i]['id'];
                                opt.innerHTML = e[i]['name']+" "+e[i]['surname'];
                                craftsman_id.appendChild(opt);
                            }
                        }
                    },
                    error: function (e) {
                        console.log("Error:", e);
                    },
                });

            }

        //თარიღების გამოტანა
            function schedule(){

                
                $("#date").datepicker("destroy");
                $("#time_start").empty();
                $("#date").val('');


                var craftsman_id = $('#craftsman_id').val();
                $.ajaxSetup({ headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") } });
                $.ajax({
                    type: "GET",
                    url: '{{url('reservation-schedule')}}/'+craftsman_id+'',
                    data: { 
                    },
                    success: function (e) {

                        var datesForDisable = e;
                        $("#date").datepicker({
                              todayBtn:  1,
                              autoclose: true,
                              format: 'yyyy-mm-dd',
                              todayHighlight:true,
                              startDate: "now()",
                              beforeShowDay: function (currentDate) {
                                var dayNr = currentDate.getDay();
                                var dateNr = moment(currentDate.getDate()).format("DD-MM-YYYY");
                                if (datesForDisable.length > 0) {
                                     for (var i = 0; i < datesForDisable.length; i++) {                        
                                       if (moment(currentDate).unix()==moment(datesForDisable[i],'YYYY-MM-DD').unix()){
                                                return true;
                                           }
                                        }
                                    }
                                    return false;
                                },
           
                        }).on('changeDate', function (selected) {
                            var minDate = new Date(selected.date.valueOf());
                        });

                    },
                    error: function (e) {
                        console.log("Error:", e);
                    },
                });
            }

        //თავისუფალი დროები
            function hours(){
                $("#time_start").empty();
                var craftsman_id = $('#craftsman_id').val(),
                    time = document.getElementById('time_start'),
                    date = $('#date').val();
                $.ajaxSetup({ headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") } });
                $.ajax({
                    type: "GET",
                    url: '{{url('reservation-hours')}}/'+craftsman_id+'',
                    data: { 
                        date: date
                    },
                    success: function (e) {
                        console.log(e);
                        if(e.length){
                            var opt = document.createElement('option');
                            opt.value = "";
                            opt.innerHTML = "{{__('აირჩიეთ')}}";
                            time.appendChild(opt);

                            for(var i=0; i<e.length; i++){
                                
                                var opt = document.createElement('option');
                                //თუ მოვიდა რეზერვირებული დრო
                                var val = e[i];
                                var option_value = val.toString().split(" ");
                                opt.value =option_value[0];
                                opt.innerHTML = e[i];
                                time.appendChild(opt);
                           
                            }
                        }
                    },
                    error: function (e) {
                        console.log("Error:", e);
                    },
                });

            }

        
    </script>
@endpush
