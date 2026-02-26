@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Welcome, {{ $user->name }}</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Total balance (USD)</div>
                    <div class="card-body">
                        <h3>${{ number_format($totalBalanceUsd, 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Your wallets</h2>
        <div class="row">
            @foreach($wallets as $wallet)
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">{{ $wallet->currency }}</div>
                        <div class="card-body">
                            <p>Balance: {{ $wallet->balance }} {{ $wallet->currency }}</p>
                            <p>Address: <small>{{ $wallet->address }}</small></p>
                            <a href="{{ route('wallet.show', $wallet->currency) }}" class="btn btn-sm btn-primary">Manage</a>
                        </div>
                    </div>
                    @endforeach
                </div>
        </div>
@endsection
