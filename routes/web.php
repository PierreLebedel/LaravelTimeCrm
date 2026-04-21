<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::calendar')->name('calendar');
Route::livewire('/clients', 'pages::clients')->name('clients');
Route::livewire('/projects', 'pages::projects')->name('projects');
Route::livewire('/agendas', 'pages::calendars')->name('calendars');
Route::livewire('/revue', 'pages::review')->name('review');
Route::livewire('/analyse', 'pages::reports')->name('reports');
Route::livewire('/queue', 'pages::queue')->name('queue');
