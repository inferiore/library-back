<?php

namespace App\Http\Requests\Borrowing;

use Illuminate\Foundation\Http\FormRequest;

class BorrowBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Both members and librarians can borrow books
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|exists:books,id',
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required' => 'Please select a book to borrow.',
            'book_id.exists' => 'The selected book does not exist.',
        ];
    }
}
