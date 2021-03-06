<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use DB;
use Auth;
use Session;
use App\User;
use Validator;
use App\Option;
use App\Survey;
use App\Student;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class SurveyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth', ['only' => ['store', 'edit', 'update', 'destroy']]);        
        $this->middleware('guest', ['only' => ['vote']]);

        $surveys = Survey::all();
        $surveys->each(function ($item, $key)
        {
            if ($item->start > Carbon::now()) $item->status = 'pending';
            elseif ($item->start <= Carbon::now() && $item->end >= Carbon::now()) $item->status = 'active';
            elseif ($item->end < Carbon::now()) $item->status = 'expired';
            else $item->status = 'expired';
            $item->save();
        });
    }

    public function index()
    {
        $carbon = new Carbon;


        $surveys = Survey::orderBy('updated_at', 'desc')->paginate(15);

        $votes = [];

        foreach ($surveys as $survey) {
            $totalVotes = 0;
            $options = Option::where('survey_id', $survey->id)->get();
            foreach ($options as $option) $totalVotes += count($option->students);
            $votes[$survey->id] = $totalVotes;
        }

        $count = $this->countSurveys();

        return view('surveys.index')->withSurveys($surveys)->withVotes($votes)->withCount($count);
    }

    public function active()
    {
        $carbon = new Carbon;

        $surveys = Survey::where('status', 'active')->orderBy('updated_at', 'desc')->paginate(15);

        $votes = [];

        foreach ($surveys as $survey) {
            $totalVotes = 0;
            $options = Option::where('survey_id', $survey->id)->get();
            foreach ($options as $option) $totalVotes += count($option->students);
            $votes[$survey->id] = $totalVotes;
        }

        $count = $this->countSurveys();

        return view('surveys.index')->withSurveys($surveys)->withVotes($votes)->withCount($count);
    }

    public function pending()
    {
        $carbon = new Carbon;

        $surveys = Survey::where('status', 'pending')->orderBy('updated_at', 'desc')->paginate(15);

        $votes = [];

        foreach ($surveys as $survey) {
            $totalVotes = 0;
            $options = Option::where('survey_id', $survey->id)->get();
            foreach ($options as $option) $totalVotes += count($option->students);
            $votes[$survey->id] = $totalVotes;
        }

        $count = $this->countSurveys();

        return view('surveys.index')->withSurveys($surveys)->withVotes($votes)->withCount($count);
    }

    public function expired()
    {
        $carbon = new Carbon;

        $surveys = Survey::where('status', 'expired')->orderBy('updated_at', 'desc')->paginate(15);

        $votes = [];

        foreach ($surveys as $survey) {
            $totalVotes = 0;
            $options = Option::where('survey_id', $survey->id)->get();
            foreach ($options as $option) $totalVotes += count($option->students);
            $votes[$survey->id] = $totalVotes;
        }

        $count = $this->countSurveys();

        return view('surveys.index')->withSurveys($surveys)->withVotes($votes)->withCount($count);
    }

    public function store(Request $request)
    {
        $request->answers = collect($request->answers)->filter(function($answer){
            return trim($answer) != '';
        });

        $rules = [
            'title' => 'required|regex:/[\s\_\-\:\.\,\?\\\\\/\'\"\%\&\#\@\!\(\)0-9A-zÑñ]{1,255}/|max:255',
            'desc' => 'required',
            'type' => 'required',
            'start' => 'required|date|after:' . new Carbon,
            'end' => 'required|date|after:' . Carbon::parse($request->start),
            'answers.*' => 'required_without:answers.0,answers.1|regex:/[\s\_\-\:\.\,\?\\\\\/\'\"\%\&\#\@\!\(\)0-9A-zÑñ]{1,25}/|max:25'
        ];

        $messages = [
            'title.required' => 'Please enter the title!',
            'title.regex' => 'Some characters are not accepted!',
            'title.max' => 'Maximum of 255 characters only!',
            'desc.required' => 'Please enter the description!',
            'type.required' => 'Please select one option!',
            'start.required' => 'Please enter a valid date!',
            'start.date' => 'Please enter a valid date!',
            'start.after' => 'Please enter time after now!',
            'end.required' => 'Please enter a valid date!',
            'end.date' => 'Please enter a valid date!',
            'end.after' => 'Please enter time after the start time!',
            'answers.*.required_without' => 'Please enter atleast two possible answers!',
            'answers.*.regex' => 'Some characters are not accepted!',
            'answers.*.max' => 'Maximum of 25 characters only!',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) return dd($validator);// back()
/*            ->withTitle($request->title)
            ->withDesc($request->desc)
            ->withStart($request->start)
            ->withEnd($request->end)
            ->withType($request->type)
            ->withErrors($validator);*/
        else {
            $survey = new Survey;
            $survey->user_id = Auth::user()->id;
            $survey->title = trim($request->title);
            $survey->desc = trim($request->desc);
            $survey->start = date('Y-m-d H:m:s', strtotime($request->start));
            $survey->end = date('Y-m-d H:m:s', strtotime($request->end));
            $survey->status = trim($request->status);
            $survey->type = trim($request->type);
            $survey->save();

            foreach ($request->answers as $answer) {
                $option = new Option;
                $option->survey_id = $survey->id;
                $option->answer = $answer;
                $option->save();
            }

            if($request->hasFile('image')) {
                $image = new FileController;
                
                if(!$image->postImage($request, 'surveys', $survey->id)) {
                    Session::flash('error', 'Error uploading photo!');
                    return back();
                }
            }
        }

        Session::flash('success', 'Your survey was successfully created.');

        return back();
    }

    public function show($id)
    {
        $carbon = new Carbon;

        $totalVotes = 0;

        $survey = Survey::findOrFail($id);
        $options = Option::where('survey_id', $id)->get();
        foreach ($options as $option) $totalVotes += count($option->students);
        foreach ($options as $option) {
            if($totalVotes == 0) $votes[$option->id] = 0;
            else {
                $votes[$option->id] = (count($option->students) / $totalVotes) * 100;
            }
        }

        return view('surveys.show')->withSurvey($survey)->withVotes($votes);
    }

    public function edit($id)
    {
        $survey = Survey::findOrFail($id);
        $options = Option::where('survey_id', $id)->get();

        $start = Carbon::parse($survey->start);
        $sm = $start->month;
        $sd = $start->day;
        $sy = $start->year;
        $sh = $start->hour;
        $smin = $start->minute;
        $sap = $sh > 12 ? 'pm' : 'am';

        $end = Carbon::parse($survey->end);
        $em = $end->month;
        $ed = $end->day;
        $ey = $end->year;
        $eh = $end->hour;
        $emin = $end->minute;
        $eap = $eh > 12 ? 'pm' : 'am';

        return view('surveys.edit')
            ->withSurvey($survey)
            ->withOptions($options)
            ->withSm($sm)
            ->withSd($sd)
            ->withSy($sy)
            ->withSh($sh)
            ->withSmin($smin)
            ->withSap($sap)
            ->withEm($em)
            ->withEd($ed)
            ->withEy($ey)
            ->withEh($eh)
            ->withEmin($emin)
            ->withEap($eap);
    }

    public function update(Request $request, $id)
    {
        $answers = collect($request->answers)->filter(function($answer){
            $answer = trim($answer);
            return $answer != '';
        });

        if ($answers->isEmpty() || count($answers) > 2) {

            Session::flash('error_ans', 'Invalid answers!');
            return redirect('surveys/' . $id . '/edit')
                ->withTitle($request->title)
                ->withDesc($request->desc)
                ->withSm($request->sm)
                ->withSd($request->sd)
                ->withSy($request->sy)
                ->withSh($request->sh)
                ->withSmin($request->smin)
                ->withSap($request->sap)
                ->withEm($request->em)
                ->withEd($request->ed)
                ->withEy($request->ey)
                ->withEh($request->eh)
                ->withEmin($request->emin)
                ->withEap($request->eap)
                ->withType($request->type);
        }

        $rules = [
            'title' => 'required|regex:/[\s\_\-\:\.\,\?\\\\\/\'\"\%\&\#\@\!\(\)0-9A-zÑñ]{1,255}/|max:255',
            'desc' => 'required',
            'type' => 'required',
            'sm' => 'required|numeric|max:12',
            'sd' => 'required|numeric|max:31',
            'sy' => 'required|numeric|max:2050',
            'sh' => 'numeric|max:12',
            'smin' => 'numeric|max:59',
            'sap' => 'regex:/[AaPp][Mm]/|max:2',
            'em' => 'required|numeric|max:12',
            'ed' => 'required|numeric|max:31',
            'ey' => 'required|numeric|max:2050',
            'eh' => 'numeric|max:12',
            'emin' => 'numeric|max:59',
            'eap' => 'regex:/[AaPp][Mm]/|max:2',
        ];

        $messages = [
            'title.required' => 'Please enter the title!',
            'title.regex' => 'Some characters are not accepted!',
            'title.max' => 'Maximum of 255 characters only!',
            'desc.required' => 'Please enter the description!',
            'type.required' => 'Please select one option!',
            'sm.*' => 'Please enter a valid date!',
            'sd.*' => 'Please enter a valid date!',
            'sy.*' => 'Please enter a valid date!',
            'sh.*' => 'Please enter a valid time!',
            'smin.*' => 'Please enter a valid time!',
            'sap.*' => 'Please enter a valid time!',
            'em.*' => 'Please enter a valid date!',
            'ed.*' => 'Please enter a valid date!',
            'ey.*' => 'Please enter a valid date!',
            'eh.*' => 'Please enter a valid time!',
            'emin.*' => 'Please enter a valid time!',
            'eap.*' => 'Please enter a valid time!',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) return redirect('surveys/' . $id . '/edit')
            ->withTitle($request->title)
            ->withDesc($request->desc)
            ->withSm($request->sm)
            ->withSd($request->sd)
            ->withSy($request->sy)
            ->withSh($request->sh)
            ->withSmin($request->smin)
            ->withSap($request->sap)
            ->withEm($request->em)
            ->withEd($request->ed)
            ->withEy($request->ey)
            ->withEh($request->eh)
            ->withEmin($request->emin)
            ->withEap($request->eap)
            ->withType($request->type)
            ->withErrors($validator);
        else {
            $sm = trim($request->sm) == '' ? 1 : trim($request->sm);
            $sd = trim($request->sd) == '' ? 1 : trim($request->sd);
            $sy = trim($request->sy) == '' ? 2000 : trim($request->sy);
            $sh = trim($request->sh) == '' ? 0 : trim($request->sh);
            $smin = trim($request->smin) == '' ? 0 : trim($request->smin);

            $sh = ($request->sap == 'am' || $request->sap == 'AM') ? $sh : (($request->sap == 'pm' || $request->sap == 'PM') ? $sh + 12 : $sh);

            $start = "$sy-$sm-$sd $sh:$smin:00";


            $em = trim($request->em) == '' ? 1 : trim($request->em);
            $ed = trim($request->ed) == '' ? 1 : trim($request->ed);
            $ey = trim($request->ey) == '' ? 2000 : trim($request->ey);
            $eh = trim($request->eh) == '' ? 0 : trim($request->eh);
            $emin = trim($request->emin) == '' ? 0 : trim($request->emin);

            $eh = ($request->eap == 'am' || $request->eap == 'AM') ? $eh : (($request->eap == 'pm' || $request->eap == 'PM') ? $eh + 12 : $eh);

            $end = "$ey-$em-$ed $eh:$emin:00";

            if (strtotime($start) <= time()) {
                $errors['start'] = 'Starting date must not beyond current date and time!';
                return redirect('surveys/' . $id . '/edit')
                    ->withTitle($request->title)
                    ->withDesc($request->desc)
                    ->withSm($request->sm)
                    ->withSd($request->sd)
                    ->withSy($request->sy)
                    ->withSh($request->sh)
                    ->withSmin($request->smin)
                    ->withSap($request->sap)
                    ->withEm($request->em)
                    ->withEd($request->ed)
                    ->withEy($request->ey)
                    ->withEh($request->eh)
                    ->withEmin($request->emin)
                    ->withEap($request->eap)
                    ->withType($request->type)
                    ->withErrors($errors);
            } elseif (strtotime($end) <= strtotime($start)) {
                $errors['end'] = 'End date must not beyond the starting date and time!';
                return redirect('surveys/' . $id . '/edit')
                    ->withTitle($request->title)
                    ->withDesc($request->desc)
                    ->withSm($request->sm)
                    ->withSd($request->sd)
                    ->withSy($request->sy)
                    ->withSh($request->sh)
                    ->withSmin($request->smin)
                    ->withSap($request->sap)
                    ->withEm($request->em)
                    ->withEd($request->ed)
                    ->withEy($request->ey)
                    ->withEh($request->eh)
                    ->withEmin($request->emin)
                    ->withEap($request->eap)
                    ->withType($request->type)
                    ->withErrors($errors);
            }

            $survey = Survey::findOrFail($id);
            $survey->title = trim($request->title);
            $survey->desc = trim($request->desc);
            $survey->start = $start;
            $survey->end = $end;
            $survey->status = trim($request->status);
            $survey->type = trim($request->type);
            $survey->save();

            $options = Option::where('survey_id', $id)->get();

            if ($options->count() == count($answers)) {
                foreach ($options as $option) {
                    $option->answer = $answers[$option->id];
                    $option->save();
                }
            } elseif ($options->count() < count($answers)) {
                foreach ($options as $option) {
                    $option->answer = $answers[$option->id];
                    $option->save();
                    array_forget($answers, $option->id);
                }
                foreach ($answers as $answer) {
                    $option = new Option;
                    $option->survey_id = $survey->id;
                    $option->answer = $answer;
                    $option->save();
                }
            } elseif ($options->count() > count($answers)) {
                foreach ($options as $option) {
                    if (array_has($answers, $option->id)) {
                        $option->answer = $answers[$option->id];
                        $option->save();
                        array_forget($options, $option->id);
                    } else {
                        Option::where('id', $option->id)->delete();
                    }
                }
            }
        }

        Session::flash('success', 'Your survey was successfully updated.');

        return redirect()->route('surveys.show', $survey->id);
    }

    public function destroy($id)
    {
        $survey = Survey::findOrFail($id)->delete();
        $title = $survey->title;

        if($survey->user_id != Auth::user()->id) return redirect()->route('surveys.show', $survey->id);

        $options = Option::where('survey_id', $survey->id)->get();
        foreach ($options as $option) {
            DB::table('option_student')->where('option_id', $option->id)->delete();
            $option->delete();
        }

        Session::flash('success', "The survey <b>$title</b> was successfully deleted.");

        return redirect()->route('surveys.index');
    }

    private function countSurveys() {
        $count['all'] = Survey::all()->count();
        $count['active'] = Survey::where('status', 'active')->get()->count();
        $count['pending'] = Survey::where('status', 'pending')->get()->count();
        $count['expired'] = Survey::where('status', 'expired')->get()->count();

        return $count;
    }

    public function vote(Request $request, $id)
    {
        if ($request->options == null) {
            $errors['options[]'] = 'Choose an option!';
            return redirect('surveys/' . $id)->withInput()->withErrors($errors);
        }

        $rules = [
            'student_id' => 'required|digits:7|exists:students,id',
            'fname' => 'required|exists:students,fname,id,' . $request->student_id,
            'lname' => 'required|exists:students,lname,id,' . $request->student_id,
        ];

        $messages = [
            'student_id.required' => 'Please enter a valid ID!',
            'student_id.digits' => 'Input must be seven digits!',
            'student_id.exists' => 'ID number not found!',
            'fname.required' => 'Please enter a valid name!',
            'fname.exists' => 'Your first name was not found!',
            'mname.exists' => 'Your middle name was not found!',
            'lname.required' => 'Please enter a valid name!',
            'lname.exists' => 'Your last name was not found!',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) return redirect('surveys/' . $id)->withErrors($validator)->withInput();
        else {
            
            $survey = Survey::findOrFail($id);
            $options = Option::where('survey_id', $survey->id)->get();

            foreach ($options as $option) {
                foreach ($option->students as $student) {
                    if ($student->id == $request->student_id) {
                        Session::flash('error', 'You can only vote <b>ONCE</b>!');
                        return redirect('surveys/' . $id);
                    }
                }
            }

            foreach ($request->options as $option) {
                DB::table('option_student')->insert([
                    'student_id' => $request->student_id,
                    'option_id' => $option,
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'updated_at' => date('Y-m-d H:i:s', time()),
                ]);
            }
        }
        Session::flash('success', 'Thank you for your cooperation!');

        return redirect('surveys/' . $id);
    }
}
