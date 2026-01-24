@extends('coach.config.layout')

@section('config_content')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Inicio</h2>
            <p class="text-sm text-gray-600">Selecciona una opción del menú para configurar tu sistema.</p>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="{{ route('coach.config.types.index') }}"
               class="rounded-xl border border-gray-100 p-4 hover:bg-gray-50">
                <div class="text-sm font-semibold text-gray-900">Tipos</div>
                <div class="text-xs text-gray-600 mt-1">Catálogo de tipos de entrenamiento.</div>
            </a>

            <a href="{{ route('coach.config.goals.index') }}"
               class="rounded-xl border border-gray-100 p-4 hover:bg-gray-50">
                <div class="text-sm font-semibold text-gray-900">Objetivos</div>
                <div class="text-xs text-gray-600 mt-1">Catálogo de objetivos.</div>
            </a>

            <a href="{{ route('coach.config.section-types.index') }}"
               class="rounded-xl border border-gray-100 p-4 hover:bg-gray-50">
                <div class="text-sm font-semibold text-gray-900">Tipos de sección</div>
                <div class="text-xs text-gray-600 mt-1">Define tipos para secciones de entrenamientos.</div>
            </a>

            <a href="{{ route('coach.config.metrics.index') }}"
               class="rounded-xl border border-gray-100 p-4 hover:bg-gray-50">
                <div class="text-sm font-semibold text-gray-900">Métricas (catálogo)</div>
                <div class="text-xs text-gray-600 mt-1">Administra métricas disponibles.</div>
            </a>

            <a href="{{ route('coach.config.settings.coach-metrics.index') }}"
               class="rounded-xl border border-gray-100 p-4 hover:bg-gray-50">
                <div class="text-sm font-semibold text-gray-900">Métricas del coach</div>
                <div class="text-xs text-gray-600 mt-1">Activa y ordena métricas para tus atletas.</div>
            </a>
        </div>
    </div>
@endsection
