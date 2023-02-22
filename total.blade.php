@if(count($reservation)>0)
	<table width="100%" cellpadding="3" cellspacing="3" border="1">
		<thead>
			<tr>
				<th colspan="14"><b>მიმდინარე რეზერვაციები</b></th>
			</tr>
			<tr>
				<th colspan="14"><b>სულ: {{count($reservation)}}</b></th>
			</tr>
			<tr>
				<th>დაწყების თარიღი</th>
				<th>დასრულების თარიღი</th>
				<th>ხანგრძლივობა</th>
				<th>ავტომობილის სახ. ნომერი	</th>
				<th>მარკა</th>
				<th>მოდელი</th>
				<th>სერვისი</th>
				<th>სერვისის კოდი</th>
				<th>სერვისის დრო</th>
				<th>მექანიკოსი</th>
				<th>მექანიკოსის ნომერი</th>
				<th>ავტომობილის მფლობელის სახელი</th>
				<th>ავტომობილის მფლობელის გვარი</th>
				<th>ავტომობილის მფლობელის ნომერი</th>
			</tr>
		</thead>
		<tbody>
			@foreach($reservation as $value)
				<tr>
					<td>{{$value->date}} {{$value->time_start}}</td>
					<td>{{$value->date_end}} {{$value->time_end}}</td>
					<td>{{$value->services_time}}წთ</td>
					<td>{{$value->car_number}}</td>
					<td>{{$value->car_make->name}}</td>
					<td>{{$value->car_model->name}}</td>
					<td>{{$value->services->name}}</td>
					<td>{{$value->services->code}}</td>
					<td>{{$value->services->time}}</td>
					<td>{{$value->craftsman->name}} {{$value->craftsman->surname}}</td>
					<td>{{$value->craftsman->phone}}</td>
					<td>{{$value->owner_name}}</td>
					<td>{{$value->owner_surname}}</td>
					<td>{{$value->owner_phone}}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
@endif