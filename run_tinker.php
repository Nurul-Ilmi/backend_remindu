<?php App\Models\Group::first()->members()->syncWithoutDetaching(App\Models\User::where('id', '>', 2)->pluck('id'));
