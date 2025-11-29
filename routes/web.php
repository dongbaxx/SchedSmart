<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use App\Livewire\Actions\Logout                   as LogoutAction;

use App\Livewire\Registrar\Dashboard              as RegistrarDashboard;
use App\Livewire\Dean\Dashboard                   as DeanDashboard;
use App\Livewire\Head\Dashboard                   as HeadDashboard;
use App\Livewire\Faculty\Dashboard                as FacultyDashboard;


use App\Livewire\Registrar\Curricula;
use App\Livewire\Registrar\Room;
use App\Livewire\Registrar\Offering\Index as ROindex;
use App\Livewire\Registrar\Offering\History as ROhistory;

use App\Livewire\Registrar\Faculty;
use App\Livewire\Registrar\Department;
use App\Livewire\Registrar\Course;
use App\Livewire\Registrar\Specialization;
use App\Livewire\Registrar\Section;
use App\Livewire\Registrar\Academic;

use App\Livewire\Head\Faculties;
use App\Livewire\Head\Spec;
use App\Livewire\Head\Offerings\Index as HOindex;
use App\Livewire\Head\Offerings\History as  HOhistory;
use App\Livewire\Head\Offerings\BlkGenerate;
use App\Livewire\Head\Schedulings;
use App\Livewire\Head\Loads;
use App\Livewire\Head\UsersLoads;

use App\Livewire\Dean\People;
use App\Livewire\Dean\Special;
use App\Livewire\Dean\Offers;
use App\Livewire\Dean\Load;
use App\Livewire\Dean\Depworkloads;


use App\Models\User;

/**
 * Home -> centralize to /dashboard
 */
Route::get('/', fn () => to_route('dashboard'))->name('home');

/**
 * Global /dashboard (single source of truth)
 * Redirects users to the correct dashboard based on role.
 */
Route::get('/dashboard', function () {
    $user = Auth::user();
    if (!$user) {
        return to_route('login');
    }

    return match ($user->role) {
        User::ROLE_REGISTRAR   => to_route('registrar.dashboard'),
        User::ROLE_DEAN        => to_route('dean.dashboard'),
        User::ROLE_HEAD        => to_route('head.dashboard'),
        User::ROLE_FACULTY     => to_route('faculty.dashboard'),
        default                => abort(403, 'Unauthorized.'),
    };
})->middleware('auth')->name('dashboard');

/** Guest auth (Volt) */
Volt::route('login', 'auth.login')->middleware('guest')->name('login');
Volt::route('forgot-password', 'auth.forgot-password')->middleware('guest')->name('password.request');
Volt::route('reset-password', 'auth.reset-password')->middleware('guest')->name('password.reset');


Volt::route('settings/profile', 'settings.profile')->middleware('auth')->name('settings.profile');
Route::post('logout', LogoutAction::class)->middleware('auth')->name('logout');

/**
 * Registrar-only area
 */
Route::middleware(['auth','role:Registrar'])->group(function () {
   Route::prefix('registrar')->name('registrar.')->group(function () {

    Route::get('/dashboard',                              RegistrarDashboard::class)->name('dashboard');

    Route::get('/curricula',                              Curricula\Index::class)->name('curricula.index');
    Route::get('/curricula/add',                          Curricula\Form::class)->name('curricula.form');
    Route::get('/curricula/{curriculum}/edit',            Curricula\Edit::class)->name('curricula.edit');

    Route::get('/rooms',                                  Room\Index::class)->name('room.index');
    Route::get('/rooms/add',                              Room\Form::class)->name('room.form');
    Route::get('/rooms/{room}/edit',                      Room\Edit::class)->name('room.edit');

    Route::get('/offering',                               ROindex::class)->name('offering.index');
    Route::get('/history',                               ROhistory::class)->name('offering.history');

    Route::get('/faculty',                                Faculty\Index::class)->name('faculty.index');
    Route::get('/faculty/create',                         Faculty\Create::class)->name('faculty.create');

    Route::get('/department',                             Department\Index::class)->name('department.index');
    Route::get('/department/create',                      Department\Create::class)->name('department.create');
    Route::get('/department/{department}/edit',           Department\Edit::class)->name('department.edit');

    Route::get('/course',                                 Course\Index::class)->name('course.index');
    Route::get('/course/create',                          Course\Create::class)->name('course.create');
    Route::get('/course/{course}/edit',                   Course\Edit::class)->name('course.edit');

    // Specializations
    Route::get('/specialization',                         Specialization\Index::class)->name('specialization.index');
    Route::get('/specialization/create',                  Specialization\Create::class)->name('specialization.create');
    Route::get('/specialization/{specialization}/edit',   Specialization\Edit::class)->name('specialization.edit');

    // Sections
    Route::get('/sections',                               Section\Index::class)->name('section.index');
    Route::get('/sections/add',                           Section\Create::class)->name('section.create');
    Route::get('/sections/{section}/edit',                Section\Edit::class)->name('section.edit');

    Route::get('/academic',                               Academic\Index::class)->name('academic.index');
    Route::get('/academic/add',                           Academic\Add::class)->name('academic.add');
    Route::get('/academic/{academic}/edit',               Academic\Edit::class)->name('academic.edit');



   });


});


Route::middleware(['auth','role:Dean'])->group(function () {
    Route::prefix('dean')->name('dean.')->group(function () {
        Route::get('/dashboard',                         DeanDashboard::class)->name('dashboard');

        Route::get('/people',                             People\Index::class)->name('people.index');
        Route::get('/people/add',                         People\Create::class)->name('people.create');
        Route::get('/people/{user}/manage',               People\Manage::class)->name('people.manage');
        Route::get('/people/{user}/specializations',      People\Specializations::class)->name('people.specializations');


        Route::get('/special',                            Special\Index::class)->name('special.index');
        Route::get('/special/add',                        Special\Create::class)->name('special.create');
        Route::get('/special/{specialization}/edit',      Special\Edit::class)->name('special.edit');

        Route::get('/offers',                              Offers\Index::class)->name('offers.index');
        Route::get('/history',                             Offers\History::class)->name('offers.history');


        Route::get('/load',                                  Load::class)->name('load');
        Route::get('/depworkloads',                                  Depworkloads::class)->name('depworkloads');

    });

});

Route::middleware(['auth','role:Head'])->group(function () {
    Route::prefix('head')->name('head.')->group(function () {

    Route::get('/dashboard',                              HeadDashboard::class)->name('dashboard');

    Route::get('/loads',                                  Loads::class)->name('loads');
    Route::get('/usersloads',                             UsersLoads::class)->name('usersloads');

    Route::get('/faculties',                              Faculties\Index::class)->name('faculties.index');
    Route::get('/faculties/add',                          Faculties\Create::class)->name('faculties.create');
    Route::get('/faculties/{user}',                       Faculties\Manage::class)->name('faculties.manage');
    Route::get('/faculties/{user}/spec',                  Faculties\Specializations::class)->name('faculties.specializations');

      // Specializations
    Route::get('/spec',                                   Spec\Index::class)->name('spec.index');
    Route::get('/spec/create',                            Spec\Create::class)->name('spec.create');
    Route::get('/spec/{specialization}/edit',             Spec\Edit::class)->name('spec.edit');

     //Course Offering
    Route::get('/offerings',                              HOindex::class)->name('offerings.index');
    Route::get('/history',                                HOhistory::class)->name('offerings.history');

    Route::get('/offerings/blk-generate',                 BlkGenerate\Wizards::class)->name('offerings.wizards');
    Route::get('schedulings/{offering}',                  Schedulings\Editor::class)->name('schedulings.editor');




    });

});

Route::middleware(['auth','role:Faculty'])->group(function () {
    Route::get('/faculty/dashboard', FacultyDashboard::class)->name('faculty.dashboard');
});

/** Fallback */
Route::fallback(fn () => response('404 Not Found', 404));
