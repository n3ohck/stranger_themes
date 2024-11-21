<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return backpack_auth()->check();
    }

    public function rules()
    {
        $rules = [
            'first_name' => 'required|min:3|max:75',
            'last_name' => 'nullable|min:3|max:75',
            'phone' => 'nullable|min:3|max:75',
            'user' => 'string|min:3|max:25|unique:users,user,' . $this->id,
            'email' => 'email|nullable',
            'password' => 'confirmed',
            'birthday' => 'nullable|date',
            'phone_ext' => 'nullable|min:3|max:75',
            'company_position' => 'nullable|min:3|max:75',
            'profile_image' => 'nullable',
            'sucursal_id' => 'required'
        ];

        if (!$this->id) {
            $rules['password'] = 'required|confirmed';
        }

        if ($this->email) {
            $rules['email'] = 'required|email|unique:' . config('permission.table_names.users', 'users') . ',email,' . $this->id;
        }

        return $rules;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'first_name.required' => 'El nombre es requerido.',
            'first_name.min' => 'El nombre debe contener al menos 5 caracteres.',
            'first_name.max' => 'El nombre debe contener maximo 255 caracteres.',
            'sucursal_id.required' => 'La sucursal es requerida.'
        ];
    }
}
