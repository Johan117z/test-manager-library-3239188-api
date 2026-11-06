<?php

namespace App\Domain\User;

enum Role: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';
}
