@extends('layouts.app')

@section('content')
<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <h1 class="text-2xl font-bold mb-6">
            Panel Admin – Dashboard
        </h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Total Coaches -->
            <div class="bg-white shadow rounded-lg p-6">
                <div class="text-sm text-gray-500">Total de Coaches</div>
                <div class="text-3xl font-bold mt-2">
                    {{ $coachesTotal }}
                </div>
            </div>

            <!-- Coaches Activos -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                <div class="text-sm text-green-700">Coaches Activos</div>
                <div class="text-3xl font-bold text-green-800 mt-2">
                    {{ $activeCoaches }}
                </div>
            </div>

            <!-- Coaches Suspendidos -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="text-sm text-red-700">Coaches Suspendidos</div>
                <div class="text-3xl font-bold text-red-800 mt-2">
                    {{ $suspendedCoaches }}
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
