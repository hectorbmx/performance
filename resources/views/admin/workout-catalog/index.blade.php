<x-app-layout><main class="max-w-5xl mx-auto space-y-4">
<h1 class="text-2xl font-bold">Catálogo GYM de plataforma</h1>
<p>Revisa datos, equipo y modalidad antes de publicar. Los privados de coaches permanecen privados.</p>
<p>La cobertura inicial del constructor es tren inferior, hipertrofia y nivel intermedio. Solo la modalidad total de barra admite sugerencias numéricas en este piloto; las demás permiten registrar repeticiones y esfuerzo sin carga.</p>
@foreach($exercises as $exercise)<a class="block rounded-xl border p-4 bg-white" href="{{route('admin.workout-catalog.edit',$exercise)}}"><strong>{{$exercise->names['es']}}</strong><br>{{$exercise->code}} · {{$exercise->review_status==='reviewed'&&$exercise->is_active?'Publicado':'Pendiente / retirado'}}</a>@endforeach
{{$exercises->links()}}</main></x-app-layout>
