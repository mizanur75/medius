<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Enums\TransEnums;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function deposit()
    {
        return view('deposit');
    }

    public function deposit_store(Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();
            $tranx = new Transaction();
            $tranx->user_id = Auth::user()->id;
            $tranx->amount = $request->amount;
            $tranx->transaction_type = TransEnums::Deposit;
            $tranx->save();
            $user = User::findOrFail(Auth::user()->id);
            $user->update(['balance' => $user->balance + $request->amount]);
            DB::commit();

            return redirect()->route('home')->with('status', 'Deposit Success!');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()->with('status', 'Something Went wrong!');
        }

    }

    public function withdrawal()
    {
        return view('withdrawal');
    }

    public function withdrawal_store(Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
        ]);

        $user = User::findOrFail(Auth::user()->id);

        if ($request->amount >= $user->balance) {
            return back()->with('status', 'Insufficient Balance');
        }

        $fee = (Auth::user()->account_type->value === 'Individual' ? 0.015/100 : 0.025/100);

        // Individual account withdraw condition
        if (Auth::user()->account_type->value === 'Individual') {
            $checkFriday = date('N') === 5;

            if ($checkFriday || $request->amount <= 1000) {
                $fee = 0;
            } else {
                $remainingAmount = $request->amount - 1000;
                $remainingFee = $remainingAmount * $fee;
                
                //Check total amount in current month
                $totalWithdrawalThisMonth = Transaction::where('user_id', $user->id)
                ->where('transaction_type', 'Withdrawal')
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum('amount');

                //Check total amount and request amount less or more than 5k in current month
                $remainingMonthlyFreeWithdrawalAmount = 5000 - $totalWithdrawalThisMonth;

                //Check remaining amount from 5k in current month
                if ($remainingMonthlyFreeWithdrawalAmount > 0) {
                    $remainingFee = $remainingFee - ($remainingMonthlyFreeWithdrawalAmount * $fee);
                }
                $fee = $remainingFee;
            }

        } else {
            // Business account withdraw condition
            $totalWithdrawal = Transaction::where('user_id', $user->id)
                ->where('transaction_type', 'Withdrawal')
                ->sum('amount');

            if ($totalWithdrawal >= 50000) {
                // decrease withdraw fee after total amount above 50k
                $withdrawalRate = 0.015/100;
            } else {
                $withdrawalRate = 0.025/100;
            }

            $fee = $request->amount * $withdrawalRate;
        }

        try {
            if ($user->balance < $request->amount + $fee) {
                return back()->with('status', 'Insufficient Balance');
            }
            DB::beginTransaction();
            $tranx = new Transaction();
            $tranx->user_id = $user->id;
            $tranx->amount = $request->amount;
            $tranx->fee = $fee;
            $tranx->transaction_type = TransEnums::Withdrawal;
            $tranx->save();
            $user->update(['balance' => $user->balance - $request->amount - $fee]);
            DB::commit();

            return redirect()->route('home')->with('status', 'Withdrawn Success!');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()->with('status', 'Something Went wrong!');
        }
    }
}
