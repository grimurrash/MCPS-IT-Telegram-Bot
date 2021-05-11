<?php

namespace App\Http\Controllers;

use App\Classes\TokenCache;
use App\Models\Member;
use App\Models\Task;
use App\Models\TaskMembers;
use Carbon\Carbon;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\PlannerTask;
use Microsoft\Graph\Model\PlannerTaskDetails;
use Telegram\Bot\Laravel\Facades\Telegram;

class PlannerController extends Controller
{
    private function getGraph(): Graph
    {
        // Get the access token from the cache
        $tokenCache = new TokenCache();
        $accessToken = $tokenCache->getAccessToken();

        // Create a Graph client
        $graph = new Graph();
        $graph->setAccessToken($accessToken);
        return $graph;
    }

    public function planner(): void
    {
        $viewData = $this->loadViewData();

        //  Id чата, группы, планнера
        $chatId = env('CHAT_ID');
//        $groupId = env('GROUP_ID');
        $planId = env('PLAN_ID');

        //  Получение задач из планера
        $graph = $this->getGraph();
        $plannerTasks = $graph->createRequest('GET', "/planner/plans/$planId/tasks")
            ->setReturnType(PlannerTask::class)
            ->execute();

        // Получение задач и пользователей из базы
        $tasks = Task::all();
        $allMembers = Member::all();

        foreach ($plannerTasks as $plannerTask) {
            $taskId = $plannerTask->getId();

            $title = $plannerTask->getTitle();
            $percentComplete = $plannerTask->getPercentComplete();
            $referenceCount = $plannerTask->getReferenceCount();
            $checklistItemCount = $plannerTask->getChecklistItemCount();
            $activeChecklistItemCount = $plannerTask->getActiveChecklistItemCount();
            $memberKeys = array_keys($plannerTask->getAssignments()->getProperties());
            $dueDateTime = $plannerTask->getDueDateTime();
            $createUserId = $plannerTask->getCreatedBy()->getUser()->getId();

            $task = $tasks->find($taskId);

            //  Новая задача
            if ($task === null) {
                $members = $allMembers->whereIn('id', $memberKeys)->pluck('username')->toArray();
                $createMember = $allMembers->find($createUserId);

                $taskDetails = $graph->createRequest('GET', "/planner/tasks/$taskId/details")
                    ->setReturnType(PlannerTaskDetails::class)
                    ->execute();
                $taskDescription = $taskDetails->getDescription();

                $html_text = implode(', ', $members) .
                    ($percentComplete !== 100 ? "\n\n‼️Новая Задача ‼️" : "\n\n‼️Задача выполнена ‼️") .
                    "\n\n<b>Задача:</b> " . $title .
                    "\n<b>Добавил:</b> " . $createMember->fullName .
                    "\n<b>Срок выполнения:</b> " . ($dueDateTime === null ? 'Отсуствует' : $dueDateTime->format('d.m.Y')) .
                    "\n<b>Заметки:</b> <i>" . ($taskDescription === '' ? '-' : $taskDescription) . '</i>';

                $checkList = $taskDetails->getChecklist()->getProperties();
                if (!empty($checkList)) {
                    $html_text .= "\n<b>Контрольный список:</b>";
                    foreach ($checkList as $checkListItem) {
                        $html_text .= "\n " . ($checkListItem['isChecked'] ? "\xE2\x9C\x85" : "\xE2\x9D\x8C") . ' ' . $checkListItem['title'];
                    }
                }
                $html_text .= "\n\n<a href='https://tasks.office.com/cpvs.moscow/ru/Home/Planner#/plantaskboard?groupId=adfbf721-4c87-45a9-8563-47f481cfd429&planId=xlf6-B2uxUCfNSrhL1SAz5cAFB_B'>Ссылка на планнер</a>";


                $response = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $html_text,
                    'parse_mode' => 'html'
                ]);
                $messageId = $response->getMessageId();
                $task = Task::create([
                    'id' => $taskId,
                    'telegramMessageId' => $messageId,
                    'title' => $title,
                    'percentComplete' => $percentComplete,
                    'referenceCount' => $referenceCount,
                    'checklistItemCount' => $checklistItemCount,
                    'activeChecklistItemCount' => $activeChecklistItemCount,
                ]);

                foreach ($memberKeys as $memberId) {
                    TaskMembers::query()->create([
                        'task_id' => $task->id,
                        'member_id' => $memberId
                    ]);
                }

                continue;
            }

            //  Завершенные задачи пропускаем
            if ($percentComplete === 100 && $task->percentComplete === 100) {
                continue;
            }

            $taskMembers = $task->members->pluck('id')->toArray();
            $isUpdate = false;
            $html_text = '';

            if ($title !== $task->title) {
                $isUpdate = true;
                $html_text .= "‼️Изменение в наименовании задачи ‼️";
            }
            if ($checklistItemCount !== $task->checklistItemCount) {
                $isUpdate = true;
                $html_text .= "‼️Изменение в контрольном списке ‼️";
            }
            if (!empty(array_diff($taskMembers, $memberKeys)) || !empty(array_diff($memberKeys, $taskMembers))) {
                $isUpdate = true;
                $html_text .= "‼️Изменение в исполнителях ‼️";
            }
            if ($percentComplete === 100 && $task->percentComplete !== 100) {
                $isUpdate = true;
                $html_text .= "‼️Задача выполнена ‼️";
            }

            //  Изменение задачи
            if ($isUpdate) {
                $members = $allMembers->whereIn('id', $memberKeys)->pluck('username')->toArray();
                $createMember = $allMembers->where('id', $createUserId)->first();

                $taskDetails = $graph->createRequest('GET', "/planner/tasks/$taskId/details")
                    ->setReturnType(PlannerTaskDetails::class)
                    ->execute();
                $taskDescription = $taskDetails->getDescription();
                $html_text = implode(', ', $members) .
                    "\n\n" . $html_text .
                    "\n\n<b>Задача:</b> " . $title .
                    "\n<b>Добавил:</b> " . $createMember->fullName .
                    "\n<b>Срок выполнения:</b> " . ($dueDateTime === null ? 'Отсуствует' : $dueDateTime->format('d.m.Y')) .
                    "\n<b>Заметки:</b> <i>" . ($taskDescription === '' ? '-' : $taskDescription) . '</i>';


                $checkList = $taskDetails->getChecklist()->getProperties();
                if (!empty($checkList)) {
                    $html_text .= "\n<b>Контрольный список:</b>";
                    foreach ($checkList as $checkListItem) {
                        $html_text .= "\n " . ($checkListItem['isChecked'] ? "\xE2\x9C\x85" : "\xE2\x9D\x8C") . ' ' . $checkListItem['title'];
                    }
                }

                $completedBy = $plannerTask->getCompletedBy()->getProperties() !== null ? $plannerTask->getCompletedBy()->getUser()->getId() : null;
                if ($completedBy !== null) {
                    $completedMember = Member::query()->find($completedBy);
                    $html_text .= "\n<b>Завершена пользователем:</b> " . $completedMember->fullName .
                        "\n<b>Дата завершения:</b> " . $plannerTask->getCompletedDateTime()->add(new \DateInterval("PT3H"))->format('d.m.Y H:i');
                }

                $html_text .= "\n\n<a href='https://tasks.office.com/cpvs.moscow/ru/Home/Planner#/plantaskboard?groupId=adfbf721-4c87-45a9-8563-47f481cfd429&planId=xlf6-B2uxUCfNSrhL1SAz5cAFB_B'>Ссылка на планнер</a>";

                $response = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $html_text,
                    'parse_mode' => 'html',
                    'reply_to_message_id' => $task->telegramMessageId
                ]);
                $messageId = $response->getMessageId();
                TaskMembers::query()->where('task_id', '=', $task->id)->delete();

                foreach ($memberKeys as $memberId) {
                    TaskMembers::query()->create([
                        'task_id' => $task->id,
                        'member_id' => $memberId
                    ]);
                }
                $task->update([
                    'id' => $taskId,
                    'telegramMessageId' => $messageId,
                    'title' => $title,
                    'percentComplete' => $percentComplete,
                    'referenceCount' => $referenceCount,
                    'checklistItemCount' => $checklistItemCount,
                    'activeChecklistItemCount' => $activeChecklistItemCount,
                ]);
            }
        }
    }
}
