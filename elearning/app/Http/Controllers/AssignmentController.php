<?php

namespace App\Http\Controllers;

use App\Models\assignment;
use App\Models\File;
use App\Models\Subject;
use App\View\Components\AssignmentCard;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    /**
     *  Checking deadline of the assignment
     */
    public static function checkDeadline($deadline, $id)
    {
        $end_time = strtotime($deadline); // Countdown end time
        $current_time = time(); // Current timestamp
        $time_left = $end_time - $current_time; // Time remaining in seconds
        $assignment = assignment::find($id);

        if($time_left <= 0 && $assignment->status != 'closed' && $assignment->status != 'open'  && $assignment->status != 'reserve'):
            $assignment->status = 'closed';
            $assignment->save();
        elseif($time_left <= 0 && $assignment->status != 'closed' && $assignment->status != 'open'):
            $assignment->status = 'hasDeadline';
            $assignment->save();
        endif;

        return $time_left;
    }

    /**
     * Slug name conversion
     */
    private function urlConvertion($name, $grade)
    {
        $result = null;
        $unslug_name = str_replace('-', ' ', $name);
        $raws = Subject::all();

        foreach($raws as $raw):
            $slug_raw = Str::slug($raw->subject_name);
            $unslug_raw = str_replace('-', ' ', $slug_raw);
            if($unslug_raw === $unslug_name):
                $result = $raw->subject_name;
                break;
            endif;
        endforeach;

        if($result == null):
            abort(401);
        endif;

        $id = Subject::where('subject_name', $result)->where('grade_id', $grade)->first();

        return $id;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * close an assignment. mark it as a completed
     */
    public function close($assignment_id, Request $request)
    {
        $assignment = Assignment::find($assignment_id);

        $assignment->status = $request->status;

        $assignment->save();

        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $assignment = new Assignment;
        $assignment->assignment_title = $request->title;
        $assignment->subject_id = $request->subject;
        $assignment->description = $request->description;
        $assignment->from_date = $request->from_date;
        $assignment->due_date = $request->due_date;
        $assignment->status = $request->class_material;
        $assignment->save();

        return back();
    }

    /**
     * Display the specified resource.
     */
    public function show($grade, $subject, $assigment_id)
    {
        $subject_id = $this->urlConvertion($subject, $grade);

        $subject = Subject::find($subject_id->id);

        $assignments = Assignment::find($assigment_id);

        $files = DB::table('files as a')
            ->select('a.*', 'b.student_name', 'b.id as student_id')
            ->leftJoin('students as b', 'a.user_id', '=', 'b.user_id')
            ->where('a.assign_by', 'student')
            ->where('a.assignment_id', $assigment_id)
            ->get();

        $teacher_file = File::where('assignment_id',  $assigment_id)
                        ->where('assign_by', 'teacher')
                        ->get();
        
        $remain_time = $this->checkDeadline($assignments->due_date, $assigment_id);

        if(!Auth::user()->role && $assignments->status == 'reserve')
            abort(401);

        return view('assignment-page', ['subject' => $subject, 'assignment' => $assignments, 'files' => $files, 'teacher_files' => $teacher_file, 'remain_time' => $remain_time ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function editAsTeacher($grade, $subject,  $assigment_id)
    {
        $subject_id = $this->urlConvertion($subject, $grade);
        
        $subject = Subject::find($subject_id->id);

        $teacher = Auth::user()->teacherData;
        
        if($subject->teacher_id != $teacher->id):
            abort(401);
        endif;

        $assignment = assignment::find($assigment_id);

        $teacher_file = File::where('assignment_id',  $assigment_id)
                            ->where('assign_by', 'teacher')
                            ->get();

        return view('edit-assignment', ['subject' => $subject, 'assignment' => $assignment, 'teacher_files' => $teacher_file, 'grade' => $grade]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function updateAsTeacher(Request $request, assignment $assignment)
    {
        $assignment->assignment_title = $request->title;
        $assignment->description = $request->description;
        $assignment->save();

        return redirect()->back()->with('msg', 'Title dan/atau Description');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(assignment $assignment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, assignment $assignment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(assignment $assignment)
    {
        assignment::destroy($assignment->id);
        return redirect('dashboard/');
    }
}
