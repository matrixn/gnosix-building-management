<?php

return [
    'array' => 'Select a valid list.',
    'numeric' => 'Enter a valid number.',
    'boolean' => 'Select a valid option.',
    'exists' => 'Select an available record in this organization.',
    'distinct' => 'Remove duplicate selections.',
    'image' => 'Upload a valid image.',
    'mimes' => 'Allowed file types: :values.',
    'dimensions' => 'Use an image no larger than 2048 × 2048 pixels.',

    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be text.',
    'email' => 'Enter a valid email address.',
    'unique' => 'This email address is already in use.',
    'confirmed' => 'The password confirmation does not match.',
    'current_password' => 'The current password is incorrect.',
    'accepted' => 'Confirm that you have read the privacy notice.',
    'in' => 'Select a valid option.',
    'integer' => 'Enter a valid integer.',
    'min' => ['numeric' => 'The value must be at least :min.', 'string' => 'The :attribute field must have at least :min characters.'],
    'max' => ['numeric' => 'Maximum :max.', 'file' => 'Maximum :max KB.', 'array' => 'Maximum :max.', 'string' => 'The :attribute field must not exceed :max characters.'],
    'attributes' => ['name' => 'name', 'email' => 'email', 'password' => 'password', 'password_confirmation' => 'password confirmation', 'current_password' => 'current password', 'profile_type' => 'profile', 'locale' => 'language'],
];
