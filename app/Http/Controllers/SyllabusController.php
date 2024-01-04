<?php

namespace App\Http\Controllers;

use App\Models\DocumentToLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class SyllabusController extends Controller
{


    public function saveSyllabus(Request $request)
    {
        $syllabus_id = $request->input('syllabus_id');
        $syllabus = DB::connection('front')->table('syllabus')
            ->join('education_discipline', 'syllabus.education_discipline_id', '=', 'education_discipline.id')
            ->join('language', 'education_discipline.education_language', '=', 'language.id')
            ->join('uib_departments', 'education_discipline.department_id', '=', 'uib_departments.id')
            ->join('study_level', 'education_discipline.study_level_id', '=', 'study_level.id')
            ->where('syllabus.id', $syllabus_id)
            ->orderByDesc('syllabus.id')
            ->select(
                'syllabus.*',
                'syllabus.id as syllabus_id',
                'education_discipline.*',
                'language.*',
                'uib_departments.*',
                'study_level.*'
            )
            ->first();

        if (!$syllabus) {
            return 'Силлабуса по данной дисциплине нет!';
        }

        // Получаем данные пользователя и его pps_data
        $pps_data = DB::connection('front')->table('user')
            ->leftJoin('pps_data', 'user.id', '=', 'pps_data.user_id')
            ->select('user.lastname as lastname_en', 'user.firstname as firstname_en', 'user.middlename as middlename_en', 'user.username', 'user.email', 'pps_data.lastname', 'pps_data.firstname', 'pps_data.middlename')
            ->where('user.id', $syllabus->user_id)
            ->first();

        // Получаем credit_time_norm_template для разных sp_education_work_id
        $credit_time_norm_1 = DB::connection('front')->table('credit_time_norm_template')
            ->where('department_id', $syllabus->department_id)
            ->where('value', $syllabus->credit)
            ->where('is_standard', $syllabus->is_standard)
            ->where('sp_education_work_id', 1)
            ->first();
        $credit_time_norm_2 = DB::connection('front')->table('credit_time_norm_template')
            ->where('department_id', $syllabus->department_id)
            ->where('value', $syllabus->credit)
            ->where('is_standard', $syllabus->is_standard)
            ->where('sp_education_work_id', 2)
            ->first();

        $credit_time_norm_5 = DB::connection('front')->table('credit_time_norm_template')
            ->where('department_id', $syllabus->department_id)
            ->where('value', $syllabus->credit)
            ->where('is_standard', $syllabus->is_standard)
            ->where('sp_education_work_id', 5)
            ->first();

        $credit_time_norm_6 = DB::connection('front')->table('credit_time_norm_template')
            ->where('department_id', $syllabus->department_id)
            ->where('value', $syllabus->credit)
            ->where('is_standard', $syllabus->is_standard)
            ->where('sp_education_work_id', 6)
            ->first();
        $sum1 = 0;
        $sum2 = 0;
        $sum5 = 0;
        $sum6 = 0;
        $office_hours = DB::connection('front')->table('syllabus_schedule_office_hour')
            ->where('syllabus_id', $syllabus->syllabus_id)
            ->join('sp_days', 'syllabus_schedule_office_hour.day_id', '=', 'sp_days.id')
            ->join('schedule_interval_time', 'syllabus_schedule_office_hour.interval_id', '=', 'schedule_interval_time.id')
            ->select('sp_days.name_ru as day',
                'schedule_interval_time.time as time'
            )
            ->get();
        $officeHourString = "";

        foreach ($office_hours as $hour) {
            $officeHourString .= $hour->day . ' ' . $hour->time . ' ';
        }


// Вычисление сумм
        for ($i = 1; $i <= 15; $i++) {
            $sum1 += $credit_time_norm_1->{'w_' . $i};
            $sum2 += $credit_time_norm_2->{'w_' . $i};
            $sum5 += $credit_time_norm_5->{'w_' . $i};
            $sum6 += $credit_time_norm_6->{'w_' . $i};
        }

// Запрос prerequisites
        $prerequisites = DB::connection('front')->table('syllabus_requisites')
            ->select(
                'syllabus_requisites.syllabus_id',
                'syllabus_requisites.discipline_id',
                'syllabus_requisites.type',
                'education_discipline.name AS discipline_name'
            )
            ->join('education_discipline', 'syllabus_requisites.discipline_id', '=', 'education_discipline.id')
            ->where('syllabus_requisites.syllabus_id', $syllabus->syllabus_id)
            ->where('syllabus_requisites.type', 1)
            ->get();

// Запрос postrequisites
        $postrequisites = DB::connection('front')->table('syllabus_requisites')
            ->select(
                'syllabus_requisites.syllabus_id',
                'syllabus_requisites.discipline_id',
                'syllabus_requisites.type',
                'education_discipline.name AS discipline_name'
            )
            ->join('education_discipline', 'syllabus_requisites.discipline_id', '=', 'education_discipline.id')
            ->where('syllabus_requisites.syllabus_id', $syllabus->syllabus_id)
            ->where('syllabus_requisites.type', 2)
            ->get();
        $main_tasks = DB::connection('front')->table('syllabus_main_tasks')
            ->where('syllabus_id', $syllabus->syllabus_id)
            ->get();

// Обработка prerequisites и postrequisites
        $prerequisitesData = "";
        $postrequisitesData = "";

        foreach ($prerequisites as $prerequisite) {
            $prerequisitesData .= mb_strtolower($prerequisite->discipline_name) . ', ';
        }

        foreach ($postrequisites as $postrequisite) {
            $postrequisitesData .= mb_strtolower($postrequisite->discipline_name) . ', ';
        }
        $syllabus_practice = DB::connection('front')->table('syllabus_practice')
            ->where('syllabus_id', $syllabus->syllabus_id)
            ->get();
        if ($syllabus && $syllabus->education_language == 137) {
            $syllabus_content = DB::connection('front')->table('syllabus_content')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->first();
            $syllabus_main_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 1)
                ->get();

            $syllabus_add_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 2)
                ->get();
            $eval1 = $syllabus->evaluation_option_id == 1 ?
                '<p><ins><b>Вариант 1. Стандарты ACCA, CFA</b></ins></p>
                 <p>Контрольный срез знаний - 40%</p>
                 <p>Активность на лекциях - 30%</p>
                 <p>Практические занятия, СРС - 30%</p>' : " ";

            $eval2 = $syllabus->evaluation_option_id == 2 ?
                '<p><ins><b>Вариант 2. Языковые дисциплины</b></ins></p>
                 <p>Практические занятия (активное участие на занятиях) - 15%</p>
                 <p>СРСП (домашние задания) - 15%</p>
                 <p>СРС (ROS и/или проектная работа) - 30%</p>
                 <p>Рубежный контроль - 30%</p>
                 <p>Тесты (промежуточный блиц контроль, по окончанию темы или раздела) - 10%</p>' : " ";

            $eval3 = $syllabus->evaluation_option_id == 3 ?
                '<p><ins><b>Вариант 3. Blended</b></ins></p>
                 <p>Активность на занятиях - 10%</p>
                 <p>Самостоятельная проработка видеолекций с прохождением тестов по модулям - 20%</p>
                 <p>Дискуссионная лекция с текущим контролем quick quiz - 20%</p>
                 <p>Практические занятия и решение задач - 10%</p>
                 <p>Контрольный срез знаний* - 20%</p>
                 <p>Письменная работа по ROS с проверкой на антиплагиат - 20%</p>' : " ";

            $eval4 = $syllabus->evaluation_option_id == 4 ?
                '<p><ins><b>Вариант 4</b><ins></p>
                 <p>Лекция - 10%</p>
                 <p>Практические занятие, СРСП - 25%</p>
                 <p>Письменная работа по ROS с проверкой на антиплагиат - 20%</p>
                 <p>Выполнение заданий СРС - 15%</p>
                 <p>Контрольный срез знаний* - 30%</p>' : " ";

            $eval5 = $syllabus->evaluation_option_id == 5 ?
                '<p><ins><b>Вариант 5</b><ins></p>
                 <p>Лекции, самостоятельная проработка материалов с прохождением тестов в Moodle - 10%</p>
                 <p>Практические занятия - 30%</p>
                 <p>СРСП: Письменная работа по ROS с проверкой на антиплагиат - 30%</p>
                 <p>Выполнение заданий СРС - 30%</p>' . '' : " ";
            $content = '
        <table style="width: 100%;" >
            <tr>
                <td style="width: 40%; text-align:center;">КЕНЖЕҒАЛИ САҒАДИЕВ АТЫНДАҒЫ ХАЛЫҚАРАЛЫҚ БИЗНЕС УНИВЕРСИТЕТІ</td>
                <td style="width: 20%; text-align:center;"><img src="https://back.uib.kz/main-images/gerb.png" style="width: 75px;"/></td>
                <td style="width: 40%; text-align:center;">УНИВЕРСИТЕТ МЕЖДУНАРОДНОГО БИЗНЕСА ИМЕНИ КЕНЖЕГАЛИ САГАДИЕВА</td>
            </tr>
        </table>
<h1 align="center">Силлабус</h1>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">

<tr style=" width: 100%;">
<td style="font-weight: bold;border:1px solid black; width: 30%;" ><p>Название дисциплины</p>
</td><td style="border:1px solid black; width: 70%;" ><p>' . $syllabus->name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Ответственный преподаватель</p>
</td><td style="border:1px solid black;" ><p>' . ucfirst(strtolower($pps_data->lastname)) . ' ' . ucfirst(strtolower($pps_data->firstname)) . ' ' . ucfirst(strtolower($pps_data->middlename)) . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Контактные данные</p>
</td><td style="border:1px solid black;" ><p>' . $pps_data->username . '@uib.kz' . ', ' . $pps_data->email . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Кафедра</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->name_ru . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Язык обучения</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->native_name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Уровень</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->name . '</p></td>
</tr>

<tr style="
    width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Пререквизиты</p>
</td><td style="border:1px solid black;" >' . substr_replace($prerequisitesData, "", -2) . '</td>
</tr>
<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Постреквизиты</p></td>
<td style="border:1px solid black;">' . substr_replace($postrequisitesData, "", -2) . '</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Переодичность предложения</p>
</td><td style="border:1px solid black;"><p>Один семестр</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Длительность</p>
</td><td style="border:1px solid black;"><p>15 недель</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Количество часов в неделю</p></td>
<td style="border:1px solid black;">
<p>Лекция - ' . $sum1 . '</p>
<p>Семинар - ' . $sum2 . '</p>
<p>СРСП(СРМП) - ' . $sum5 . '</p>
<p>СРС - ' . $sum6 . '</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Офисные часы</p></td>
<td style="border:1px solid black;">
<p>' . $officeHourString . '</p>
<p>Для посещения офисного часа необходимо предварительное согласование с преподавателем</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>EСTS кредиты</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->credit . '</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Форма итогового контроля</p></td>
<td style="border:1px solid black;"><p>' . '' . '</p>
</td>
</tr>

<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Описание дисциплины</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->description . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Результаты обучения</p></td>
<td style="border:1px solid black;"><p>' . '<i>1. По завершении курса студент должен знать: </i><br>' . $syllabus->knowledge . '<br>' . '<i>2. По завершении курса студент должен уметь: </i><br>' . $syllabus->abilities . '<br>' . '<i>3. Личные и ключевые навыки: </i><br>' . $syllabus->skills . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Требования курса</p></td>
<td style="border:1px solid black;"><p>'
                . '1. К каждому аудиторному занятию вы должны подготовиться заранее. Темы занятии приведены ниже в разделе «Содержание дисциплины».' . '<br>'
                . '2. Задания будут загружены в учебный портал (https://moodle.uib.kz) в течение семестра, с указанием сроков сдачи.' . '<br>'
                . '3. Задания должны выполняться в указанные сроки. Позже задания будут приняты с коэффициентом (0,8-через неделю, 0,5-через две недели? 3 и более – 0,3).' . '<br>'
                . '4. За 20% пропуска аудиторных занятии без уважительной причины, преподаватель имеет право не допустить студента к итоговому контролю (экзамен) и отправить на летний семестр.' . '<br>
' . '</p>
</td>
</tr>

<tr style=" width: 100%;">
<td style=" font-weight: bold; border:1px solid black;"><p>Политика оценки</p></td>
<td style="border:1px solid black;">
' . $eval1 . '

' . $eval2 . '

' . $eval3 . '

' . $eval4 . '

' . $eval5 . '

</td>
</tr>

<tr style="width: 100%;">
<td style="font-weight: bold; border:1px solid black;"><p>Оценка</p></td>
<td style="border:1px solid black;">
<p>Оценка знаний студентов осуществляется по балльно-рейтинговой буквенной системе с соответствующим переводом в традиционную шкалу оценок.</p>
<p><b>Шкала выставления рейтинга обучающегося:</b></p>
<p>Ваша итоговая оценка будет рассчитываться по формуле</p>
<p>Итоговая оценка по дисциплине = </p>
<img src="https://back.uib.kz/main-images/formula_rus.png" style="width: 180px;" alt="Оценка"/>
<p>Ниже приведены минимальные оценки в процентах:</p>
<table style="border:1px solid black;" width="100%" align="center">
<tr style="">
<th style="font-weight: bold;" ><p>Оценка по буквенной системе</p></th>
<th style="" ><p>Цифровой эквивалент</p></th>
<th style="" ><p>Баллы (%-ное содержание)</p></th>
<th style="" ><p>Оценка традиционной системе</p></th>
</th>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А</p></td>
<td style="" ><p>4,0</p></td>
<td style="" ><p>95% - 100%</p></td>
<td style="" ><p>Отлично</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А-</p></td>
<td style="" ><p>3,67</p></td>
<td style="" ><p>90% - 94%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В+</p></td>
<td style="" ><p>3,33</p></td>
<td style="" ><p>85% - 89%</p></td>
<td style="" ><p>Хорошо</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В</p></td>
<td style="" ><p>3,0</p></td>
<td style="" ><p>80% - 84%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В-</p></td>
<td style="" ><p>2,67</p></td>
<td style="" ><p>75% - 79%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С+</p></td>
<td style="" ><p>2,33</p></td>
<td style="" ><p>70% - 74%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С</p></td>
<td style="" ><p>2,0</p></td>
<td style="" ><p>65% - 69%</p></td>
<td style="" ><p>Удовлетворительно</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С-</p></td>
<td style="" ><p>1,67</p></td>
<td style="" ><p>60% - 64%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D+</p></td>
<td style="" ><p>1,33</p></td>
<td style="" ><p>55% - 59%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D-</p></td>
<td style="" ><p>1,0</p></td>
<td style="" ><p>50% - 54%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>FХ</p></td>
<td style="" ><p>0,5</p></td>
<td style="" ><p>25% - 49%</p></td>
<td style="" ><p>Неудовлетворительно</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>F</p></td>
<td style="" ><p>0</p></td>
<td style="" ><p>0% - 24%</p></td>
<td style="" ><p></p></td>
</tr>
</table>
</td>
</tr>
</table>
<br>
';
            $content2 = '
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black;" align="center" colspan="3"><h3><b>Содержание дисциплины</b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 12%;"><p>Неделя</p></th>
<th style="border:1px solid black; width: 78%;"><p>Название темы</p></th>
<th style="border:1px solid black; width: 10%;"><p>Часы</p></th>
</th>
</tr>
';


            for ($i = 1; $i <= 15; $i++) {
                $syllabus_questions = DB::connection('front')->table('syllabus_questions')
                    ->where('syllabus_id', $syllabus->syllabus_id)
                    ->where('week', $i)
                    ->get();
                $questionString = "";
                if (count($syllabus_questions) > 0) {
                    foreach ($syllabus_questions as $question) {
                        $questionString .= $question->text . ';';
                    }
                }
                $content2 .= '
<tr style="width: 100%; ">
<td style=" font-weight: bold; border:1px solid black; text-align: center;" rowspan="5"><p>' . $i . ' неделя</p></td>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Лекция. ' . '</i>' . $syllabus_practice[$i - 1]->lecture_text . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="2"><p>' . $syllabus_practice[$i - 1]->lecture_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Рассматриваемые вопросы: ' . '</i>' . $questionString . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Практика(семинар): ' . '</i>' . $syllabus_practice[$i - 1]->practice . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="3"><p>' . $syllabus_practice[$i - 1]->seminar_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Задание на семинар: ' . '</i>' . $syllabus_practice[$i - 1]->seminar_task . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Материалы к чтению: ' . '</i>' . $syllabus_practice[$i - 1]->material . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
';
            }

            $content2 .= '';

            $content3 = '';

            if ($syllabus_main_literature) {
                $content3 = ' <tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Базовая литература</b></h3></th>';
                $literature_number = 1;
                foreach ($syllabus_main_literature as $literature_main) {

                    $content3 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $literature_number . '. ' . $literature_main->title . ', ' . $literature_main->author . ', ' . $literature_main->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $literature_number++;
                }
            }

            $content4 = '';

            if ($syllabus_add_literature) {
                $content4 = '<tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Дополнительная литература</b></h3></th>';
                $add_literature_number = 1;
                foreach ($syllabus_add_literature as $literature_add) {
                    $content4 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $add_literature_number . '. ' . $literature_add->title . ', ' . $literature_add->author . ', ' . $literature_add->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $add_literature_number++;
                }
            }
            $content5 = '
</table>
<br>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black;" align="center" colspan="4"><h3><b>Основные задания в рамках курса </b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 15%;"><p>Задание</p></th>
<th style="border:1px solid black; width: 50%;"><p>Описание</p></th>
<th style="border:1px solid black; width: 20%;"><p>Период сдачи (Deadline)</p></th>
<th style="border:1px solid black; width: 15%;"><p>Критерии оценки</p></th>
</th>
</tr>
';
            foreach ($main_tasks as $main_task) {
                $content5 .= '
<tr style="width: 100%; ">
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->task . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->description . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->deadline . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->criterions . '</p></td>
</tr>
';
            }
            $content5 .= '</table>
<br>
<p>Силлабус (Syllabus) составлен на основании утвержденного каталога элективных дисциплин.</p>
<p>Силлабус (Syllabus) составил(а) _______________________</p>
';

        } elseif ($syllabus && $syllabus->education_language == 82) {
            $office_hours = DB::connection('front')->table('syllabus_schedule_office_hour')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->join('sp_days', 'syllabus_schedule_office_hour.day_id', '=', 'sp_days.id')
                ->join('schedule_interval_time', 'syllabus_schedule_office_hour.interval_id', '=', 'schedule_interval_time.id')
                ->select('sp_days.name_kz as day',
                    'schedule_interval_time.time as time'
                )
                ->get();
            $officeHourString = "";

            foreach ($office_hours as $hour) {
                $officeHourString .= $hour->day . ' ' . $hour->time . ' ';
            }

            $syllabus_content = DB::connection('front')->table('syllabus_content')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->first();

            $syllabus_main_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 1)
                ->get();

            $syllabus_add_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 2)
                ->get();


            $eval1 = $syllabus->evaluation_option_id == 1 ? '<p><ins><b>1-нұсқа. ACCA, CFA стандарттары</b></ins></p>
<p>Бақылау жұмысы - 40%</p>
<p>Дәрістегі белсенділік - 30%</p>
<p>Практикалық жаттығулар, СӨЖ - 30%</p>' : " ";

            $eval2 = $syllabus->evaluation_option_id == 2 ? '<p><ins><b>2-нұсқа. Тілдік пәндер</b></ins></p>
<p>Практикалық жаттығулар (сабаққа белсенді қатысу) - 15%</p>
<p>СОӨЖ (үй тапсырмасы) - 15%</p>
<p>СӨЖ (ROS және/немесе жобалық жұмыс) - 30%</p>
<p>Аралық бақылау - 30%</p>
<p>Тесттер (тақырыптың немесе бөлімнің соңында, аралық блиц-бақылау) - 10%</p>' : " ";

            $eval3 = $syllabus->evaluation_option_id == 3 ? '<p><ins><b>3-нұсқа. Blended</b></ins></p>
<p>Сабақтағы белсенділік - 10%</p>
<p>Бейне дәрістерді өз бетінше оқу арқылы модуль бойынша тест тапсыру - 20%</p>
<p>Ағымдық бақылаумен дебаттық дәріс quick quiz - 20%</p>
<p>Практикалық жаттығулар және есептерді шешу - 10%</p>
<p>Бақылау жұмысы* - 20%</p>
<p>Плагиатқа тексерумен ROS бойынша жазбаша жұмыс - 20%</p>' : " ";

            $eval4 = $syllabus->evaluation_option_id == 4 ? '<p><ins><b>4-нұсқа</b><ins></p>
<p>Дәріс - 10%</p>
<p>Тәжірибелік дайындық, СОӨЖ - 25%</p>
<p>Плагиатқа тексерумен ROS бойынша жазбаша жұмыс - 20%</p>
<p>СӨЖ тапсырмаларын орындау - 15%</p>
<p>Бақылау жұмысы* - 30%</p>' : " ";

            $eval5 = $syllabus->evaluation_option_id == 5 ? '<p><ins><b>5-нұсқа</b><ins></p>
<p>Дәрістер, Moodle-да тест тапсыру арқылы материалдарды өз бетінше оқу – 10%</p>
<p>Тәжірибелік дайындық – 30%</p>
<p>СОӨЖ: Плагиатқа тексерумен ROS бойынша жазбаша жұмыс - 30%</p>
<p>СӨЖ тапсырмаларын орындау – 30%</p>' .
                '' : " ";

            $content = '
        <table style="width: 100%;" >
            <tr>
                <td style="width: 40%; text-align:center;">КЕНЖЕҒАЛИ САҒАДИЕВ АТЫНДАҒЫ ХАЛЫҚАРАЛЫҚ БИЗНЕС УНИВЕРСИТЕТІ</td>
                <td style="width: 20%; text-align:center;"><img src="https://back.uib.kz/main-images/gerb.png" style="width: 75px;"/></td>
                <td style="width: 40%; text-align:center;">УНИВЕРСИТЕТ МЕЖДУНАРОДНОГО БИЗНЕСА ИМЕНИ КЕНЖЕГАЛИ САГАДИЕВА</td>
            </tr>
        </table>
<h1 align="center">Силлабус</h1>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">

<tr style="width: 100%;">
<td style="font-weight: bold;border:1px solid black; width: 30%;" ><p>Пән атауы</p>
</td><td style="border:1px solid black; width: 70%;" ><p>' . $syllabus->name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Жауапты оқытушы</p>
</td><td style="border:1px solid black;" ><p>' . ucfirst(strtolower($pps_data->lastname)) . ' ' . ucfirst(strtolower($pps_data->firstname)) . ' ' . ucfirst(strtolower($pps_data->middlename)) . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Байланыс ақпараты</p>
</td><td style="border:1px solid black;" ><p>' . $pps_data->username . '@uib.kz' . ', ' . $pps_data->email . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Кафедра</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->name_ru . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Оқыту тілі</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->native_name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Деңгей</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->name . '</p></td>
</tr>

<tr style="
    width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Пререквизиттер</p>
</td><td style="border:1px solid black;" >' . substr_replace($prerequisitesData, "", -2) . '</td>
</tr>
<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Постреквизиттер</p></td>
<td style="border:1px solid black;">' . substr_replace($postrequisitesData, "", -2) . '</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Ұсыным жасау мерзімі</p>
</td><td style="border:1px solid black;"><p>Бір семестр</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Ұзақтығы</p>
</td><td style="border:1px solid black;"><p>15 апта</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Аптасына сағат саны</p></td>
<td style="border:1px solid black;">

<p>Дәріс - ' . $sum1 . '</p>
<p>Семинар - ' . $sum2 . '</p>
<p>СОӨЖ(МОӨЖ) - ' . $sum5 . '</p>
<p>СӨЖ - ' . $sum6 . '</p>

</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Офис сағаттар</p></td>
<td style="border:1px solid black;">
<p>' . $officeHourString . '</p>
<p>Офис сағатына қатысу үшін оқытушының алдын ала келісімі қажет.</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>EСTS кредиттері</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->credit . '</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Емтихан түрі</p></td>
<td style="border:1px solid black;"><p>' . '' . '</p>
</td>
</tr>

<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Пәннің сипаттамасы</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->description . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Білім беруде күтілетін нәтижелер</p></td>
<td style="border:1px solid black;"><p>' . '<i>1. Курс аяқтағаннан кейін студент білуі керек: </i><br>' . $syllabus->knowledge . '<br>' . '<i>2. Курс аяқтағаннан кейін студент: </i><br>' . $syllabus->abilities . '<br>' . '<i>3. Жеке және негізгі дағдылар: </i><br>' . $syllabus->skills . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Курс талаптары</p></td>
<td style="border:1px solid black;"><p>'
                . '1. Студент әрбір аудиториялық сабаққа дайындалып келуі міндетті. Әр аптадағы сабақтар тақырыбы, төменде «Пәннің мазмұны» бөлімінде көрсетілген.' . '<br>'
                . '2. Жеке тапсырмалар семестр бойында, тапсыру мерзімі көрсетіліп оқу порталына (https://moodle.uib.kz) жүктеледі.' . '<br>'
                . '3. Әр  тапсырма көрсетілген мерзімге дейін орындалып тапсырылуы міндетті. Тапсырма уақытылы орындалмаған жағдайда төмедету коэффициентәмен (1 апта кешіктірілсе коэффициент 0,8; 2 апта - 0,5; 3 апта және одан да жоғары – 0,3) қабылданады.' . '<br>'
                . '4. Студент аудиториялық сабақтың 20% себепсіз жіберіп алса, оқытушы студентті қортынды емтиханға жібермей, жаздық семестірге қалдыруға құқұлы.' . '<br>
' . '</p>
</td>
</tr>

<tr style=" width: 100%;">
<td style=" font-weight: bold; border:1px solid black;"><p>Бағалау саясаты</p></td>
<td style="border:1px solid black;">
' . $eval1 . '

' . $eval2 . '

' . $eval3 . '

' . $eval4 . '

' . $eval5 . '

</td>
</tr>

<tr style="width: 100%; ">
<td style="font-weight: bold;border:1px solid black;"><p>Білім бағалау</p></td>
<td style="border:1px solid black;">
<p><b>Ағымдағы үлгерім бақылауы академиялық бақылау кезеңінде алынған  барлық бағалардың орташа арифметикалық қосындысын есептеу
арқылы шығарылады.</b></p>
<p>Пән бойынша қорытынды баға пайыздық көрсеткіште мынадай формула бойынша анықталады: = </p>
<img src="https://back.uib.kz/main-images/formula_kaz.png" style="width: 180px;" alt="Оценка"/>
<p>мұнда: АБ1 ол 1 - рейтингтің пайыздық көрсеткіші;</p>
<p>АБ2 ол 2 - рейтингтің пайыздық көрсеткіші;</p>
<p>Е – емтихан бағасының пайыздық көрсеткіші.</p>
<p>Балдың пайыздық көрсеткіштері:</p>
<table style="border:1px solid black;" width="100%" align="center">
<tr style="">
<th style=" font-weight: bold;" ><p>Әріп түріндегі баға</p></th>
<th style="" ><p>Балдың  сандық баламасы</p></th>
<th style="" ><p>Оқу пәнін игерудің %-дық мазмұны</p></th>
<th style="" ><p>Дәстүрлі жүйе бойынша бағалау</p></th>
</th>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А</p></td>
<td style="" ><p>4,0</p></td>
<td style="" ><p>95% - 100%</p></td>
<td style="" ><p>Өте жақсы</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А-</p></td>
<td style="" ><p>3,67</p></td>
<td style="" ><p>90% - 94%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В+</p></td>
<td style="" ><p>3,33</p></td>
<td style="" ><p>85% - 89%</p></td>
<td style="" ><p>Жақсы</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В</p></td>
<td style="" ><p>3,0</p></td>
<td style="" ><p>80% - 84%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В-</p></td>
<td style="" ><p>2,67</p></td>
<td style="" ><p>75% - 79%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С+</p></td>
<td style="" ><p>2,33</p></td>
<td style="" ><p>70% - 74%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С</p></td>
<td style="" ><p>2,0</p></td>
<td style="" ><p>65% - 69%</p></td>
<td style="" ><p>Қанағаттанарлық</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С-</p></td>
<td style="" ><p>1,67</p></td>
<td style="" ><p>60% - 64%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D+</p></td>
<td style="" ><p>1,33</p></td>
<td style="" ><p>55% - 59%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D-</p></td>
<td style="" ><p>1,0</p></td>
<td style="" ><p>50% - 54%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>FХ</p></td>
<td style="" ><p>0,5</p></td>
<td style="" ><p>25% - 49%</p></td>
<td style="" ><p>Қанағаттанарлықсыз</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>F</p></td>
<td style="" ><p>0</p></td>
<td style="" ><p>0% - 24%</p></td>
<td style="" ><p></p></td>
</tr>
</table>
</td>
</tr>
</table>
<br>
';
            $content2 = '';
            if ($syllabus_content) {
                $content2 .= '
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black; " align="center" colspan="4"><h3><b>Пәннің мазмұны</b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 12%;"><p>Апта</p></th>
<th style="border:1px solid black; width: 78%;"><p>Тақырыптың атауы</p></th>
<th style="border:1px solid black; width: 10%;"><p>Сағат саны</p></th>
</th>
</tr>
';

                for ($i = 1; $i <= 15; $i++) {
                    $syllabus_questions = DB::connection('front')->table('syllabus_questions')
                        ->where('syllabus_id', $syllabus->syllabus_id)
                        ->where('week', $i)
                        ->get();
                    $questionString = "";
                    if (count($syllabus_questions) > 0) {
                        foreach ($syllabus_questions as $question) {
                            $questionString .= $question->text . ';';
                        }
                    }
                    $content2 .= '
<tr style="width: 100%; ">
<td style=" font-weight: bold; border:1px solid black; text-align: center;" rowspan="5"><p>' . $i . ' апта</p></td>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Дәріс. ' . '</i>' . $syllabus_practice[$i - 1]->lecture_text . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="2"><p>' . $syllabus_practice[$i - 1]->lecture_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Қарастырылатын сұрақтар: ' . '</i>' . $questionString . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Практика(семинар): ' . '</i>' . $syllabus_practice[$i - 1]->practice . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="3"><p>' . $syllabus_practice[$i - 1]->seminar_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Семинарлық тапсырма: ' . '</i>' . $syllabus_practice[$i - 1]->seminar_task . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Оқу материалдары: ' . '</i>' . $syllabus_practice[$i - 1]->material . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
';
                }
            }
            $content2 .= '';

            $content3 = '';

            if ($syllabus_main_literature) {
                $content3 = ' <tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Негізгі әдебиеттер</b></h3></th>';
                $literature_number = 1;
                foreach ($syllabus_main_literature as $literature_main) {

                    $content3 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $literature_number . '. ' . $literature_main->title . ', ' . $literature_main->author . ', ' . $literature_main->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $literature_number++;
                }
            }

            $content4 = '';

            if ($syllabus_add_literature) {
                $content4 = '<tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Қосымша әдебиеттер</b></h3></th>';
                $add_literature_number = 1;
                foreach ($syllabus_add_literature as $literature_add) {
                    $content4 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $add_literature_number . '. ' . $literature_add->title . ', ' . $literature_add->author . ', ' . $literature_add->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $add_literature_number++;
                }
            }
            $content5 = '
</table>
<br>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black;" align="center" colspan="4"><h3><b>Пәннің негізгі тапсырмалары </b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 15%;"><p>Тапсырма</p></th>
<th style="border:1px solid black; width: 50%;"><p>Сипаттама</p></th>
<th style="border:1px solid black; width: 20%;"><p>Тапсыру мерзімі (Deadline)</p></th>
<th style="border:1px solid black; width: 15%;"><p>Бағалау критерийлері</p></th>
</th>
</tr>
';
            foreach ($main_tasks as $main_task) {
                $content5 .= '
<tr style="width: 100%; ">
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->task . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->description . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->deadline . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->criterions . '</p></td>
</tr>
';
            }
            $content5 .= '</table>
<br>
<p>Силлабус (Syllabus) бекітілген элективті пәндер каталогы негізінде құрастырылған.</p>
<p>Құрастырушы _______________________</p>
';
        } else {
            $office_hours = DB::connection('front')->table('syllabus_schedule_office_hour')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->join('sp_days', 'syllabus_schedule_office_hour.day_id', '=', 'sp_days.id')
                ->join('schedule_interval_time', 'syllabus_schedule_office_hour.interval_id', '=', 'schedule_interval_time.id')
                ->select('sp_days.name_en as day',
                    'schedule_interval_time.time as time'
                )
                ->get();
            $officeHourString = "";

            foreach ($office_hours as $hour) {
                $officeHourString .= $hour->day . ' ' . $hour->time . ' ';
            }
            $syllabus_content = DB::connection('front')->table('syllabus_content')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->first();

            $syllabus_main_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 1)
                ->get();

            $syllabus_add_literature = DB::connection('front')->table('syllabus_literature')
                ->where('syllabus_id', $syllabus->syllabus_id)
                ->where('literature_type', 2)
                ->get();

            $eval1 = $syllabus->evaluation_option_id == 1 ? '<p><ins><b>Option 1. ACCA, CFA standards</b></ins></p>
<p>Control slice of knowledge - 40%</p>
<p>Activity at lectures - 30%</p>
<p>Practical exercises, IWS - 30%</p>' : " ";

            $eval2 = $syllabus->evaluation_option_id == 2 ? '<p><ins><b>Option 2. Language disciplines</b></ins></p>
<p>Practical classes (active participation in the classroom) - 15%</p>
<p>IWST (homework) - 15%</p>
<p>IWS (ROS and/or project work) - 30%</p>
<p>Frontier control - 30%</p>
<p>Tests (intermediate blitz control, at the end of a topic or section) - 10%</p>' : " ";

            $eval3 = $syllabus->evaluation_option_id == 3 ? '<p><ins><b>Option 3. Blended</b></ins></p>
<p>Activity in the classroom - 10%</p>
<p>Independent study of video lectures with passing tests by modules - 20%</p>
<p>Discussion lecture with current control quick quiz - 20%</p>
<p>Practical exercises and problem solving - 10%</p>
<p>Control slice of knowledge* - 20%</p>
<p>Written work on ROS with anti-plagiarism check - 20%</p>' : " ";

            $eval4 = $syllabus->evaluation_option_id == 4 ? '<p><ins><b>Option 4</b><ins></p>
<p>Lecture - 10%</p>
<p>Practical lesson, IWST - 25%</p>
<p>Written work on ROS with anti-plagiarism check - 20%</p>
<p>Completion of IWS tasks - 15%</p>
<p>Control slice of knowledge* - 30%</p>' : " ";

            $eval5 = $syllabus->evaluation_option_id == 5 ? '<p><ins><b>Option 5</b><ins></p>
<p>Lectures, independent study of materials with passing tests in Moodle - 10%</p>
<p>Practical lesson - 30%</p>
<p>IWST: Written work on ROS with anti-plagiarism check - 30%</p>
<p>Completion of IWS tasks - 30%</p>' . '' : " ";

            switch ($syllabus->study_level_id) {
                case 1:
                    $study_level = 'Bachelor';
                    break;
                case 2:
                    $study_level = 'Master (1 year, profile)';
                    break;
                case 3:
                    $study_level = 'Master (1,5 year, profile)';
                    break;
                case 4:
                    $study_level = 'Master (2 year)';
                    break;
                case 5:
                    $study_level = 'MBA';
                    break;
                case 6:
                    $study_level = 'EMBA';
                    break;
                case 7:
                    $study_level = 'Doctorate';
                    break;
                case 8:
                    $study_level = 'DBA';
                    break;
                case 9:
                    $study_level = 'Other';
                    break;
            }

            $content = '
        <table style="width: 100%;" >
            <tr>
                <td style="width: 40%; text-align:center;">КЕНЖЕҒАЛИ САҒАДИЕВ АТЫНДАҒЫ ХАЛЫҚАРАЛЫҚ БИЗНЕС УНИВЕРСИТЕТІ</td>
                <td style="width: 20%; text-align:center;"><img src="https://back.uib.kz/main-images/gerb.png" style="width: 75px;"/></td>
                <td style="width: 40%; text-align:center;">УНИВЕРСИТЕТ МЕЖДУНАРОДНОГО БИЗНЕСА ИМЕНИ КЕНЖЕГАЛИ САГАДИЕВА</td>
            </tr>
        </table>
<h1 align="center">Syllabus</h1>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">

<tr style=" width: 100%;">
<td style=" font-weight: bold; border:1px solid black; width: 30%;" ><p>Title</p></td>
<td style=" border:1px solid black; width: 70%;" ><p>' . $syllabus->name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Responsible Faculty member / Instructor</p>
</td><td style="border:1px solid black;" ><p>' . ucfirst(strtolower($pps_data->lastname_en)) . ' ' . ucfirst(strtolower($pps_data->firstname_en)) . ' ' . ucfirst(strtolower($pps_data->middlename_en)) . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Contacts</p>
</td><td style="border:1px solid black;" ><p>' . $pps_data->username . '@uib.kz' . ', ' . $pps_data->email . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Faculty</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->name_ru . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Language of Instruction</p>
</td><td style="border:1px solid black;" ><p>' . $syllabus->native_name . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Level</p>
</td><td style="border:1px solid black;" ><p>' . $study_level . '</p></td>
</tr>

<tr style="
    width: 100%; ">
<td style="font-weight: bold;border:1px solid black;" ><p>Pre-requisites</p>
</td><td style="border:1px solid black;" >' . substr_replace($prerequisitesData, "", -2) . '</td>
</tr>
<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Post-requisites</p></td>
<td style="border:1px solid black;">' . substr_replace($postrequisitesData, "", -2) . '</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Frequency of offer</p>
</td><td style="border:1px solid black;"><p>One semester</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Duration</p>
</td><td style="border:1px solid black;"><p>15 weeks</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>The amount of hours in a week</p></td>
<td style="border:1px solid black;">
<p>Lecture - ' . $sum1 . '</p>
<p>Seminar - ' . $sum2 . '</p>
<p>IWST(IWMT) - ' . $sum5 . '</p>
<p>IWS - ' . $sum6 . '</p>

</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Office hours</p></td>
<td style="border:1px solid black;">
<p>' . $officeHourString . '</p>
<p>Attending an office hour necessitates obtaining prior approval from the instructor.</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>ECTS credits</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->credit . '</p>
</td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Form of exam</p></td>
<td style="border:1px solid black;"><p>' . '' . '</p>
</td>
</tr>

<tr style="
    width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Course Description</p></td>
<td style="border:1px solid black;"><p>' . $syllabus->description . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Intended learning outcomes</p></td>
<td style="border:1px solid black;"><p>' . '<i>1. Upon completion of the course, the student should know: </i><br>' . $syllabus->knowledge . '<br>' . '<i>2. Upon completion of the course, the student should be able to: </i><br>' . $syllabus->abilities . '<br>' . '<i>3. Personal and key skills: </i><br>' . $syllabus->skills . '</p></td>
</tr>

<tr style=" width: 100%; ">
<td style=" font-weight: bold;border:1px solid black;"><p>Requirements</p></td>
<td style="border:1px solid black;"><p>'
                . '1. You must prepare in advance for each class according to the schedule below. The topics of the lessons are given below in the section "Content of the discipline". ' . '<br>'
                . '2. Assignments will be uploaded to the learning portal (https://moodle.uib.kz) throughout the semester with an indication of the deadlines.' . '<br>'
                . '3. Tasks must be completed within the specified time frame. Later tasks will be accepted with coefficient (0,8-in a week, 0,5-in two weeks).' . '<br>'
                . '4. For 20% of missed classes without good reason, teacher has the right to not let the student to take the final control (exam) and send him/her to the summer semester.' . '<br>
' . '</p>
</td>
</tr>

<tr style=" width: 100%;">
<td style=" font-weight: bold; border:1px solid black;"><p>Evaluation policy</p></td>
<td style="border:1px solid black;">
' . $eval1 . '

' . $eval2 . '

' . $eval3 . '

' . $eval4 . '

' . $eval5 . '

</td>
</tr>

<tr style="width: 100%; ">
<td style="font-weight: bold;border:1px solid black;"><p>Assessment policy</p></td>
<td style="border:1px solid black;">
<p>Assessment of students knowledge is carried out on a point-rating letter system.</p>
<p><b>Student rating scale:</b></p>
<p>Your final grade will be calculated according to the formula</p>
<p>Final grade = </p>
<img src="https://back.uib.kz/main-images/formula_eng.png" style="width: 180px;" alt="Оценка"/>
<p>Below are the minimum assessment in percent:</p>
<table style="border:1px solid black;" width="100%" align="center">
<tr style="">
<th style="font-weight: bold;"><p>Grading Scale</p></th>
<th style="" ><p>4 Point grading Scale</p></th>
<th style="" ><p>Pointsin %</p></th>
<th style="" ><p>Traditional grade</p></th>
</th>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А</p></td>
<td style="" ><p>4,0</p></td>
<td style="" ><p>95% - 100%</p></td>
<td style="" ><p>Excellent</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>А-</p></td>
<td style="" ><p>3,67</p></td>
<td style="" ><p>90% - 94%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В+</p></td>
<td style="" ><p>3,33</p></td>
<td style="" ><p>85% - 89%</p></td>
<td style="" ><p>Good</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В</p></td>
<td style="" ><p>3,0</p></td>
<td style="" ><p>80% - 84%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>В-</p></td>
<td style="" ><p>2,67</p></td>
<td style="" ><p>75% - 79%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С+</p></td>
<td style="" ><p>2,33</p></td>
<td style="" ><p>70% - 74%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С</p></td>
<td style="" ><p>2,0</p></td>
<td style="" ><p>65% - 69%</p></td>
<td style="" ><p>Satisfactory</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>С-</p></td>
<td style="" ><p>1,67</p></td>
<td style="" ><p>60% - 64%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D+</p></td>
<td style="" ><p>1,33</p></td>
<td style="" ><p>55% - 59%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>D-</p></td>
<td style="" ><p>1,0</p></td>
<td style="" ><p>50% - 54%</p></td>
<td style="" ><p></p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>FХ</p></td>
<td style="" ><p>0,5</p></td>
<td style="" ><p>25% - 49%</p></td>
<td style="" ><p>Fail</p></td>
</tr>
<tr style="">
<td style=" font-weight: bold;" ><p>F</p></td>
<td style="" ><p>0</p></td>
<td style="" ><p>0% - 24%</p></td>
<td style="" ><p></p></td>
</tr>
</table>
</td>
</tr>

</table>
<br>
';
            $content2 = '';
            if ($syllabus_content) {
                $content2 .= '
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black; " align="center" colspan="4"><h3><b>Content course</b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 12%;" ><p>Week</p></th>
<th style="border:1px solid black; width: 78%;"><p>Title of the topic</p></th>
<th style="border:1px solid black; width: 10%;"><p>Hours</p></th>
</th>
</tr>
';

                for ($i = 1; $i <= 15; $i++) {
                    $syllabus_questions = DB::connection('front')->table('syllabus_questions')
                        ->where('syllabus_id', $syllabus->syllabus_id)
                        ->where('week', $i)
                        ->get();
                    $questionString = "";
                    if (count($syllabus_questions) > 0) {
                        foreach ($syllabus_questions as $question) {
                            $questionString .= $question->text . ';';
                        }
                    }
                    $content2 .= '
<tr style="width: 100%; ">
<td style=" font-weight: bold; border:1px solid black; text-align: center;" rowspan="5"><p>Week ' . $i . ' </p></td>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Lecture. ' . '</i>' . $syllabus_practice[$i - 1]->lecture_text . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="2"><p>' . $syllabus_practice[$i - 1]->lecture_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Questions covered: ' . '</i>' . $questionString . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Practice (seminar): ' . '</i>' . $syllabus_practice[$i - 1]->practice . '</p></td>
<td style=" border:1px solid black; text-align: center;" rowspan="3"><p>' . $syllabus_practice[$i - 1]->seminar_hour . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Seminar assignment: ' . '</i>' . $syllabus_practice[$i - 1]->seminar_task . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
<tr>
<td style=" border:1px solid black;"><p>' . '<i>' . 'Reading materials: ' . '</i>' . $syllabus_practice[$i - 1]->material . '</p></td>
<td style=" border:1px solid black;"><p></p></td>
</tr>
';
                }
            }
            $content2 .= '';

            $content3 = '';

            if ($syllabus_main_literature) {
                $content3 = ' <tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Core literature</b></h3></th>';
                $literature_number = 1;
                foreach ($syllabus_main_literature as $literature_main) {

                    $content3 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $literature_number . '. ' . $literature_main->title . ', ' . $literature_main->author . ', ' . $literature_main->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $literature_number++;
                }
            }

            $content4 = '';

            if ($syllabus_add_literature) {
                $content4 = '<tr><th style="border:1px solid black;" align="center" colspan="4"><h3><b>Supplementary literature and texts</b></h3></th>';
                $add_literature_number = 1;
                foreach ($syllabus_add_literature as $literature_add) {
                    $content4 .= '
</tr>
<tr style="width: 100%; ">
<td style=" border:1px solid black;"colspan="4"><p>' . $add_literature_number . '. ' . $literature_add->title . ', ' . $literature_add->author . ', ' . $literature_add->publishing_year . '; <br>' . '</p></td>
</tr>
';
                    $add_literature_number++;
                }
            }
            $content5 = '
</table>
<br>
<table style="border:1px solid black; width: 100%; border-collapse: collapse;">
<tr>
<th style="border:1px solid black;" align="center" colspan="4"><h3><b>Major assignments of the discipline </b></h3></th>
</tr>
<tr style="">
<th style="border:1px solid black; width: 15%;"><p>Assignment</p></th>
<th style="border:1px solid black; width: 50%;"><p>Description</p></th>
<th style="border:1px solid black; width: 20%;"><p>Deadline</p></th>
<th style="border:1px solid black; width: 15%;"><p>Assessment criteria </p></th>
</th>
</tr>
';
            foreach ($main_tasks as $main_task) {
                $content5 .= '
<tr style="width: 100%; ">
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->task . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->description . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->deadline . '</p></td>
<td style=" border:1px solid black; text-align: center;"><p>' . $main_task->criterions . '</p></td>
</tr>
';
            }
            $content5 .= '</table>
<br>
<p>Syllabus was made on the basis of approved catalogue of elective courses.</p>
<p>Syllabus was prepared by_______________________</p>
';
        }
        $qr = QrCode::generate(
            'https://front.uib.kz/qr-verify?syllabus='.$syllabus_id,
        );
        $pdfContent = "<p style='text-align: center; width: 20px; height: 20px'>$qr</p>";
//        $pdfContent = "<img src=' . $qr . '>";

        // Create mPDF instance with settings
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font_size' => 11,
            'default_font' => 'DejaVuSerifCondensed',
            'tempDir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf'
        ]);

        $mpdf->SetDisplayMode('fullpage');
        $fullContent = $content . $content2 . $content3 . $content4 . $content5 . preg_replace('/<\?xml.*\?>/i', '', $pdfContent);
        $pdfFilePath = storage_path('documents/syllabus/syllabus_'.$syllabus_id.'.pdf');

// Generate PDF content using mPDF
        $mpdf->WriteHTML($fullContent, \Mpdf\HTMLParserMode::HTML_BODY);

// Save the PDF to the specified file path
        $mpdf->Output($pdfFilePath, \Mpdf\Output\Destination::FILE);

// Check if the file was created successfully
        if (file_exists($pdfFilePath)) {
                // Создание новой записи
                $newDocument = DocumentToLog::create([
                    'file_url' => $pdfFilePath,
                    'file_name' => 'syllabus_'.$syllabus_id,
                    'category_id' => 1,
                ]);
                // Другие действия после создания, например, возврат ответа или перенаправление

        } else {
            return "Failed to create the PDF file.";
        }
    }

}
