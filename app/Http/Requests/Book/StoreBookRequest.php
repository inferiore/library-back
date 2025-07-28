<?php

namespace App\Http\Requests\Book;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLibrarian() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'author' => 'required|string|max:255',
            'genre' => 'required|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'total_copies' => 'required|integer|min:1|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The book title is required.',
            'title.max' => 'The book title cannot exceed 255 characters.',
            'author.required' => 'The author name is required.',
            'author.max' => 'The author name cannot exceed 255 characters.',
            'genre.required' => 'The book genre is required.',
            'genre.max' => 'The book genre cannot exceed 255 characters.',
            'isbn.required' => 'The ISBN is required.',
            'isbn.unique' => 'A book with this ISBN already exists.',
            'total_copies.required' => 'The number of copies is required.',
            'total_copies.integer' => 'The number of copies must be a valid number.',
            'total_copies.min' => 'There must be at least 1 copy.',
            'total_copies.max' => 'Cannot exceed 1000 copies.',
        ];
    }
}