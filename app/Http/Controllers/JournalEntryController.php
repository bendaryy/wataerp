<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Utility;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    // approved for user
    public function index()
    {
        if (\Auth::user()->can('manage journal entry')) {
            $journalEntries = JournalEntry::where('created_by', '=', \Auth::user()->creatorId())->where('Approve', 1)->get();

            return view('journalEntry.index', compact('journalEntries'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    //waiting for user
    public function journalWaiting()
    {
        if (\Auth::user()->can('manage journal entry')) {
            $journalEntries = JournalEntry::where('created_by', '=', \Auth::user()->creatorId())->where('Approve', 0)->get();

            return view('journalEntry.index', compact('journalEntries'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    //waiting for admin
    public function waiting()
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntries = JournalEntry::where('Approve', 0)->get();

            return view('adminjournal.waiting', compact('journalEntries'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
// approved from admin
    public function ApprovedFromAdmin()
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntries = JournalEntry::where('Approve', 1)->get();

            return view('adminjournal.Approved', compact('journalEntries'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
// create new jouranl
    public function create()
    {
        if (\Auth::user()->can('create journal entry')) {
            $accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))->where('created_by', \Auth::user()->creatorId())->get()->pluck('code_name', 'id');
            $accounts->prepend('--', '');

            $journalId = $this->journalNumber();

            return view('journalEntry.create', compact('accounts', 'journalId'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }
// store new jouranl
    public function store(Request $request)
    {

        if (\Auth::user()->can('create invoice')) {
            $validator = \Validator::make(
                $request->all(), [
                    'date' => 'required',
                    'accounts' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $accounts = $request->accounts;

            $totalDebit = 0;
            $totalCredit = 0;
            for ($i = 0; $i < count($accounts); $i++) {
                $debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                $credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($totalCredit != $totalDebit) {
                return redirect()->back()->with('error', __('Debit and Credit must be Equal.'));
            }

            $journal = new JournalEntry();
            $journal->journal_id = $this->journalNumber();
            $journal->date = $request->date;
            $journal->reference = $request->reference;
            $journal->description = $request->description;
            $journal->Approve = 0;
            $journal->red_flag = 0;
            $journal->created_by = \Auth::user()->creatorId();
            $journal->save();

            for ($i = 0; $i < count($accounts); $i++) {
                $journalItem = new JournalItem();
                $journalItem->journal = $journal->id;
                $journalItem->account = $accounts[$i]['account'];
                $journalItem->description = $accounts[$i]['description'];
                $journalItem->debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                $journalItem->credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                $journalItem->save();
            }

            return redirect()->route('userWaitingJournal')->with('success', __('Journal entry successfully created and waiting for admin approval.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
// show journal for user
    public function show(JournalEntry $journalEntry)
    {
        if (\Auth::user()->can('show journal entry')) {
            if ($journalEntry->created_by == \Auth::user()->creatorId()) {
                $accounts = $journalEntry->accounts;
                $settings = Utility::settings();

                return view('journalEntry.view', compact('journalEntry', 'accounts', 'settings'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    // show journal for admin

    // public function showAdmin(JournalEntry $journalEntry)
    public function showAdmin($id)
    {
        // if(\Auth::user()->can('show journal entry'))
        if (\Auth::user()->type == 'super admin') {
            $journalEntry = JournalEntry::findOrFail($id);
            // if($journalEntry->created_by == \Auth::user()->creatorId())
            // {
            $accounts = $journalEntry->accounts;
            $settings = Utility::settings();

            return view('adminjournal.show', compact('journalEntry', 'accounts', 'settings'));
            // }
            // else
            // {
            //     return redirect()->back()->with('error', __('Permission denied.'));
            // }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(JournalEntry $journalEntry)
    {
        if (\Auth::user()->can('edit journal entry')) {
            $accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))->where('created_by', \Auth::user()->creatorId())->get()->pluck('code_name', 'id');
            $accounts->prepend('--', '');

            return view('journalEntry.edit', compact('accounts', 'journalEntry'));

        } else if (\Auth::user()->type == 'super admin') {
            $accounts = ChartOfAccount::select(\DB::raw('CONCAT(code, " - ", name) AS code_name, id'))->where('created_by', $journalEntry->created_by)->get()->pluck('code_name', 'id');
            $accounts->prepend('--', '');

            return view('journalEntry.edit', compact('accounts', 'journalEntry'));

        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, JournalEntry $journalEntry)
    {
        if (\Auth::user()->can('edit journal entry')) {
            if ($journalEntry->created_by == \Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(), [
                        'date' => 'required',
                        'accounts' => 'required',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $accounts = $request->accounts;

                $totalDebit = 0;
                $totalCredit = 0;
                for ($i = 0; $i < count($accounts); $i++) {
                    $debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                    $credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                if ($totalCredit != $totalDebit) {
                    return redirect()->back()->with('error', __('Debit and Credit must be Equal.'));
                }

                $journalEntry->date = $request->date;
                $journalEntry->reference = $request->reference;
                $journalEntry->description = $request->description;
                $journalEntry->Approve = 0;
                $journalEntry->created_by = \Auth::user()->creatorId();
                $journalEntry->save();

                for ($i = 0; $i < count($accounts); $i++) {
                    $journalItem = JournalItem::find($accounts[$i]['id']);

                    if ($journalItem == null) {
                        $journalItem = new JournalItem();
                        $journalItem->journal = $journalEntry->id;
                    }

                    if (isset($accounts[$i]['account'])) {
                        $journalItem->account = $accounts[$i]['account'];
                    }

                    $journalItem->description = $accounts[$i]['description'];
                    $journalItem->debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                    $journalItem->credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                    $journalItem->save();
                }

                return redirect()->route('userWaitingJournal')->with('success', __('Journal entry successfully updated and waiting for admin approval.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } elseif (\Auth::user()->type == 'super admin') {

            $validator = \Validator::make(
                $request->all(), [
                    'date' => 'required',
                    'accounts' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $accounts = $request->accounts;

            $totalDebit = 0;
            $totalCredit = 0;
            for ($i = 0; $i < count($accounts); $i++) {
                $debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                $credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($totalCredit != $totalDebit) {
                return redirect()->back()->with('error', __('Debit and Credit must be Equal.'));
            }

            $journalEntry->date = $request->date;
            $journalEntry->reference = $request->reference;
            $journalEntry->description = $request->description;
            $journalEntry->Approve = 1;
            // $journalEntry->created_by = \Auth::user()->creatorId();
            $journalEntry->save();

            for ($i = 0; $i < count($accounts); $i++) {
                $journalItem = JournalItem::find($accounts[$i]['id']);

                if ($journalItem == null) {
                    $journalItem = new JournalItem();
                    $journalItem->journal = $journalEntry->id;
                }

                if (isset($accounts[$i]['account'])) {
                    $journalItem->account = $accounts[$i]['account'];
                }

                $journalItem->description = $accounts[$i]['description'];
                $journalItem->debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
                $journalItem->credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
                $journalItem->save();
            }

            return redirect()->route('approvedjournal')->with('success', __('Journal entry successfully updated and waiting for admin approval.'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    // approve event for admin

    public function adminApprove($id)
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntry = JournalEntry::findOrFail($id);
            $journalEntry->Approve = 1;
            $journalEntry->save();
            return redirect()->route('approvedjournal')->with('success', __('Journal entry successfully approved.'));
        }
    }

    // waiting event for admin
    public function adminWaiting($id)
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntry = JournalEntry::findOrFail($id);
            $journalEntry->Approve = 0;
            $journalEntry->save();
            return redirect()->route('waitingjournal')->with('success', __('Journal entry successfully added to waiting.'));
        }
    }

    // make red falg from admin

    public function makeRedFlag($id)
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntry = JournalEntry::findOrFail($id);
            $journalEntry->red_flag = 1;
            $journalEntry->save();
            return redirect()->back()->with('success', __('Journal entry successfully red flag.'));

        }
    }
    // remove red falg by admin

    public function RemoveRedFlag($id)
    {
        if (\Auth::user()->type == 'super admin') {
            $journalEntry = JournalEntry::findOrFail($id);
            $journalEntry->red_flag = 0;
            $journalEntry->save();
            return redirect()->back()->with('success', __('Journal entry successfully red flag.'));

        }
    }

    public function destroy(JournalEntry $journalEntry)
    {
        if (\Auth::user()->can('delete journal entry') || \Auth::user()->type == 'super admin') {
            if ($journalEntry->created_by == \Auth::user()->creatorId() || \Auth::user()->type == 'super admin') {
                $journalEntry->delete();

                JournalItem::where('journal', '=', $journalEntry->id)->delete();
                if (\Auth::user()->type == 'super admin') {
                    return redirect()->back()->with('success', __('Journal entry successfully deleted.'));
                } else {
                    return redirect()->route('journal-entry.index')->with('success', __('Journal entry successfully deleted.'));
                }
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function journalNumber()
    {
        $latest = JournalEntry::where('created_by', '=', \Auth::user()->creatorId())->latest()->first();
        if (!$latest) {
            return 1;
        }

        return $latest->journal_id + 1;
    }

    public function accountDestroy(Request $request)
    {

        if (\Auth::user()->can('delete journal entry')) {
            JournalItem::where('id', '=', $request->id)->delete();

            return redirect()->back()->with('success', __('Journal entry account successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
