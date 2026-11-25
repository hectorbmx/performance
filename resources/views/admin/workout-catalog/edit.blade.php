<x-app-layout><main class="max-w-4xl mx-auto space-y-4">
<a href="{{route('admin.workout-catalog.index')}}">‹ Catálogo GYM</a><h1 class="text-2xl font-bold">{{$exercise->names['es']}}</h1><p>Código estable: {{$exercise->code}}</p>
@if(session('success'))<p role="status" class="p-4 bg-green-100">{{session('success')}}</p>@endif
@if($errors->any())<div role="alert" class="p-4 bg-red-100">@foreach($errors->all() as $error)<p>{{$error}}</p>@endforeach</div>@endif
<form class="space-y-4 bg-white p-5 rounded-xl" method="POST" action="{{route('admin.workout-catalog.update',$exercise)}}">@csrf @method('PATCH')
<input type="hidden" name="fingerprint" value="{{$fingerprint}}">
@foreach(['es'=>'Nombre en español','en'=>'Nombre en inglés'] as $locale=>$label)<label class="block">{{$label}}<input class="block w-full" name="names[{{$locale}}]" value="{{old('names.'.$locale,$exercise->names[$locale]??'')}}" @if($locale==='es')required @endif maxlength="160"></label>@endforeach
<label class="block">Modalidad de carga<select name="load_mode" class="block w-full">@foreach(['total_barbell','per_dumbbell','external_total','machine_indicated','bodyweight'] as $mode)<option @selected(old('load_mode',$exercise->load_mode)===$mode)>{{$mode}}</option>@endforeach</select></label>
<label class="block">Fatiga<select name="fatigue">@foreach(['low','moderate','high'] as $fatigue)<option @selected(old('fatigue',$exercise->fatigue)===$fatigue)>{{$fatigue}}</option>@endforeach</select></label>
@foreach(['unilateral'=>'Unilateral','is_active'=>'Activo'] as $field=>$label)<input type="hidden" name="{{$field}}" value="0"><label class="block"><input type="checkbox" name="{{$field}}" value="1" @checked(old($field,$exercise->$field))> {{$label}}</label>@endforeach
@foreach($fields as $field=>$kind)<fieldset class="border p-3"><legend>{{$field}}</legend>
<input type="hidden" name="{{$field}}" value="">
@foreach($taxonomy[$kind]??[] as $term)@php($selected=old($field,$exercise->terms->where('kind',$kind)->filter(fn($t)=>$field==='primary_muscles'?$t->pivot->role==='primary':($field==='secondary_muscles'?$t->pivot->role==='secondary':true))->pluck('code')->all()))
<label class="inline-block mr-4"><input type="checkbox" name="{{$field}}[]" value="{{$term['code']}}" @checked(in_array($term['code'],is_array($selected)?$selected:[]))> {{$term['names']['es']}}</label>@endforeach</fieldset>@endforeach
<label class="block">Notas<textarea class="block w-full" name="notes" maxlength="4000">{{old('notes',$exercise->notes)}}</textarea></label>
<label class="block">Motivo de revisión / cambio<textarea class="block w-full" name="reason" required minlength="5" maxlength="2000">{{old('reason')}}</textarea></label>
<label class="block"><input type="checkbox" name="review_confirmed" value="1"> Confirmo que revisé clasificación, equipo, modalidad y cobertura antes de publicar.</label>
<div class="flex flex-wrap gap-4"><button class="rounded bg-gray-200 p-3" name="action" value="draft">Guardar pendiente</button><button class="rounded bg-indigo-600 text-white p-3" name="action" value="publish">Guardar y publicar revisado</button><button class="rounded bg-red-100 p-3" name="action" value="withdraw">Retirar publicación</button></div></form>
<h2 class="font-bold">Auditoría reciente</h2>@foreach($reviews as $review)<p>{{$review->created_at}} · {{$review->action}} · Revisor #{{$review->reviewer_id}} · {{$review->reason}}</p>@endforeach
</main></x-app-layout>
