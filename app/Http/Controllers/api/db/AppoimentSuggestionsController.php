<?php

namespace App\Http\Controllers\api\db;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AppoimentSuggestionsController extends Controller
{
    public function getSuggestions(Request $request)
    {
        try {
            // Validamos la request
            $validated = $request->validate([
                'client_type' => 'required|in:existing,new,anonymous',
                'client_id' => 'nullable',
                'service_id' => 'required|array',
                'service_id.*' => 'integer',
                'employee_id' => 'array',
                'employee_id.*' => 'integer',
                'dayAndTime' => 'required|string',
                'group_consecutives' => 'boolean',
                'calendar_date' => 'date|nullable',
                'calendar_time' => 'date_format:H:i|nullable',
                'include_taken' => 'boolean'
            ]);
            // Primero configuramos la conexión
            $connection = $request->get('db_connection');
            if (!$connection) {
                return response()->json(['error' => 'No se especificó la conexión a la base de datos'], 400);
            }
            // Configuramos la conexión
            $query = DB::connection($connection);

            // Validamos que los servicios existan en la base de datos específica
            $servicesExist = $query->table('services')
                ->whereIn('id', $validated['service_id'])
                ->count();

            if ($servicesExist !== count($validated['service_id'])) {
                return response()->json(['error' => 'Uno o más servicios no existen'], 404);
            }

            // Si hay employee_id, validamos que existan
            if (!empty($validated['employee_id'])) {
                $employeesExist = $query->table('users')
                    ->whereIn('id', $validated['employee_id'])
                    ->count();

                if ($employeesExist !== count($validated['employee_id'])) {
                    return response()->json(['error' => 'Uno o más empleados no existen'], 404);
                }
            }

            // Resto del código...
            $totalDuration = $this->getTotalDuration($query, $validated['service_id']);
            $employees = $this->getValidEmployees($query, $validated);

            if ($employees->isEmpty()) {
                return response()->json(['message' => 'No hay empleados disponibles'], 404);
            }

            $timeSlots = $this->generateTimeSlots($query, $employees, $totalDuration, $validated);

            return response()->json(['suggestions' => $timeSlots]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'connection' => $connection ?? 'no connection specified'
            ], 500);
        }
    }

    // ================ Helper Methods ================ //




    private function getTotalDuration($query, array $serviceIds): array
    {
        return $query->table('services')
            ->whereIn('id', $serviceIds)
            ->pluck('appointment_duration_minutes', 'id')
            ->toArray();
    }

    private function getValidEmployees($query, array $validated): \Illuminate\Support\Collection
    {
        $baseQuery = $query->table('users')
            ->join('user_services', 'users.id', '=', 'user_services.user_id')
            ->whereIn('user_services.service_id', $validated['service_id'])
            ->where('users.active', true)
            ->select('users.*');

        if (!empty($validated['employee_id'])) {
            $baseQuery->whereIn('users.id', $validated['employee_id']);
        }

        return $baseQuery->distinct()->get();
    }

    private function generateTimeSlots($query, $employees, array $durations, array $validated): array
    {
        $period = $this->getDateRange($validated);
        $employeeIds = $employees->pluck('id');

        $existingAppointments = $query->table('appointments')
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$period['start'], $period['end']])
            ->get(['start_date', 'end_date', 'user_id', 'service_id']);

        $schedules = $this->getEmployeeSchedules($query, $employeeIds, $validated['service_id']);

        $allSlots = [];
        foreach ($validated['service_id'] as $serviceId) {
            $duration = $durations[$serviceId];
            $serviceSchedules = $schedules->where('service_id', $serviceId);

            $slots = $this->calculateAvailableSlots($period, $duration, $serviceSchedules, $existingAppointments, $validated, $serviceId);
            $allSlots = array_merge($allSlots, $slots);
        }

        // Si se solicita incluir los turnos tomados
        if (isset($validated['include_taken']) && $validated['include_taken']) {
            $allSlots = $this->addTakenSlotsInfo($allSlots, $existingAppointments);
        }

        // Ordenar todos los slots por fecha
        usort($allSlots, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $allSlots;
    }

    private function getDateRange(array $validated): array
    {
        $now = Carbon::now();
        $start = match ($validated['dayAndTime']) {
            'now' => $now->copy(),
            'about_now' => $now->copy()->addMinutes(30),
            '1hour' => $now->copy(),
            'tomorrow' => $now->copy()->addDay()->startOfDay(),
            'next_week' => $now->copy()->addWeek()->startOfDay(),
            'next_month' => $now->copy()->addMonth()->startOfDay(),
            '3months' => $now->copy()->addMonths(3)->startOfDay(),
            '6months' => $now->copy()->addMonths(6)->startOfDay(),
            'morning' => $now->copy()->setTime(6, 0),
            'afternoon' => $now->copy()->setTime(12, 0),
            'night' => $now->copy()->setTime(18, 0),
            default => Carbon::parse($validated['calendar_date'] . ' ' . $validated['calendar_time'])
        };

        $end = match ($validated['dayAndTime']) {
            'now' => $now->copy()->endOfDay(),
            'about_now' => $now->copy()->endOfDay(),
            '1hour' => $now->copy()->addHours(3),
            'tomorrow' => $now->copy()->addDay()->endOfDay(),
            'next_week' => $now->copy()->addWeek()->endOfDay(),
            'next_month' => $now->copy()->addMonth()->endOfDay(),
            '3months' => $now->copy()->addMonths(3)->endOfDay(),
            '6months' => $now->copy()->addMonths(6)->endOfDay(),
            'morning' => $now->copy()->setTime(12, 0),
            'afternoon' => $now->copy()->setTime(18, 0),
            'night' => $now->copy()->setTime(23, 59),
            default => Carbon::parse($validated['calendar_date'] . ' ' . $validated['calendar_time'])->addDay()
        };

        return [
            'start' => $start,
            'end' => $end,
            'period' => CarbonPeriod::create($start, $end)
        ];
    }



    private function getEmployeeSchedules($query, $employeeIds, array $serviceIds): \Illuminate\Support\Collection
    {
        return $query->table('rangos')
            ->join('user_range', 'rangos.id', '=', 'user_range.range_id')
            ->join('times_range', 'rangos.id', '=', 'times_range.range_id')
            ->whereIn('user_range.user_id', $employeeIds)
            ->whereIn('rangos.service_id', $serviceIds) // Filtrar por servicio
            ->get([
                'user_range.user_id',
                'times_range.hora_inicio',
                'times_range.hora_fim',
                'rangos.*',
                'rangos.service_id'
            ]);
    }
    private function calculateAvailableSlots(array $period, int $duration, $schedules, $appointments, array $validated, int $serviceId): array
    {
        $slots = [];
        $now = Carbon::now();
        $timeGroups = [];
    
        foreach ($period['period'] as $date) {
            foreach ($schedules as $schedule) {
                $dayName = strtolower($date->format('l'));
                if (!$schedule->$dayName) continue;
    
                $workStart = Carbon::parse($schedule->hora_inicio)->setDateFrom($date);
                $workEnd = Carbon::parse($schedule->hora_fim)->setDateFrom($date);
                $skipThisDay = false;

                // Solo para el caso "now" - tratamiento especial
                if ($validated['dayAndTime'] === 'now' && $date->isToday()) {
                    if ($now->gt($workStart)) {
                        // Primer slot basado en la hora actual
                        $firstSlotStart = $now->copy()->ceil($duration);
                        $firstSlotEnd = $firstSlotStart->copy()->addMinutes($duration);
    
                        if ($firstSlotEnd <= $workEnd && !$this->isSlotOccupied($firstSlotStart, $firstSlotEnd, $schedule->user_id, $appointments)) {
                            $timeGroups[$firstSlotStart->format('Y-m-d H:i:s')] = [
                                'start' => $firstSlotStart->toDateTimeString(),
                                'end' => $firstSlotEnd->toDateTimeString(),
                                'employee_ids' => [$schedule->user_id],
                                'service_id' => $serviceId
                            ];
    
                            // Generar slots intermedios más eficientes solo para "now"
                            $intermediateStart = $firstSlotStart->copy()->addMinutes(ceil($duration/2));
                            $intermediateEnd = $intermediateStart->copy()->addMinutes($duration);
                            
                            if ($intermediateEnd <= $workEnd && !$this->isSlotOccupied($intermediateStart, $intermediateEnd, $schedule->user_id, $appointments)) {
                                $timeGroups[$intermediateStart->format('Y-m-d H:i:s')] = [
                                    'start' => $intermediateStart->toDateTimeString(),
                                    'end' => $intermediateEnd->toDateTimeString(),
                                    'employee_ids' => [$schedule->user_id],
                                    'service_id' => $serviceId
                                ];
                            }
    
                            // Siguiente slot regular
                            $nextSlotStart = $firstSlotEnd->copy();
                            $roundedMinutes = ceil($nextSlotStart->minute / $duration) * $duration;
                            $workStart = $nextSlotStart->setMinute($roundedMinutes)->setSecond(0);
                        }
                    }
                } else {
                    // Para todos los demás casos, alinear los slots a intervalos regulares
                    if ($validated['dayAndTime'] === 'calendar' && isset($validated['calendar_date']) && isset($validated['calendar_time'])) {
                        $calendarDateTime = Carbon::parse($validated['calendar_date'] . ' ' . $validated['calendar_time']);
                        $workStart = max($workStart, $calendarDateTime);
                    }
                    
                    // Alinear el inicio a intervalos regulares
                    $minutes = $workStart->minute;
                    $roundedMinutes = ceil($minutes / $duration) * $duration;
                    if ($roundedMinutes >= 60) {
                        $workStart->addHour();
                        $roundedMinutes = 0;
                    }
                    $workStart->setMinute($roundedMinutes)->setSecond(0);
                }
    
                // Resto del switch case para ajustar horarios según dayAndTime
                switch ($validated['dayAndTime']) {
                    case 'now':
                        if ($date->isToday()) {
                            if ($now->gt($workStart)) {
                                $workStart = $now->copy()->ceil($duration);
                            }
                            // No limitamos a 1 hora para "now"
                        } else {
                            $skipThisDay = true;
                        }
                        break;
    
                    case 'about_now':
                        if ($date->isToday()) {
                            $workStart = $now->copy()->addMinutes(30)->ceil($duration);
                            $workEnd = min($workEnd, $workStart->copy()->addHours(1));
                        } else {
                            $skipThisDay = true;
                        }
                        break;
    
                    case '1hour':
                        if ($date->isToday()) {
                            $nextHour = $now->copy()->addHour()->startOfHour();
                            $workStart = max($workStart, $nextHour);
                            // Asegurar que no exceda el horario de trabajo
                            $proposedEnd = min($workStart->copy()->addHour(), $workEnd);
                            if ($proposedEnd->gt($workEnd) || $workStart->gt($workEnd)) {
                                $skipThisDay = true;
                            } else {
                                $workEnd = $proposedEnd;
                            }
                        } else {
                            $skipThisDay = true;
                        }
                        break;
    
                    case 'morning':
                        $workStart = max($workStart, $date->copy()->setTime(6, 0));
                        $workEnd = min($workEnd, $date->copy()->setTime(12, 0));
                        break;
    
                    case 'afternoon':
                        $workStart = max($workStart, $date->copy()->setTime(12, 0));
                        $workEnd = min($workEnd, $date->copy()->setTime(18, 0));
                        break;
    
                    case 'night':
                        $workStart = max($workStart, $date->copy()->setTime(18, 0));
                        $workEnd = min($workEnd, $date->copy()->setTime(23, 59));
                        break;
    
                    default:
                        // Para tomorrow, next_week, next_month, etc.
                        // Usar los horarios del empleado sin modificación
                        break;
                }
    
                if ($skipThisDay) continue;
                if ($workStart->gt($workEnd)) continue;
    
                $currentSlot = $workStart->copy();
    
                // Generación de slots
                if ($validated['dayAndTime'] === 'now' && $date->isToday()) {
                    // Para "now", mantener la generación de slots superpuestos
                    while ($currentSlot->copy()->addMinutes($duration) <= $workEnd) {
                        $slotEnd = $currentSlot->copy()->addMinutes($duration);
                        $timeKey = $currentSlot->format('Y-m-d H:i:s');
    
                        if (!isset($timeGroups[$timeKey]) && !$this->isSlotOccupied($currentSlot, $slotEnd, $schedule->user_id, $appointments)) {
                            $timeGroups[$timeKey] = [
                                'start' => $currentSlot->toDateTimeString(),
                                'end' => $slotEnd->toDateTimeString(),
                                'employee_ids' => [$schedule->user_id],
                                'service_id' => $serviceId
                            ];
    
                            // Agregar slot intermedio
                            $intermediateStart = $currentSlot->copy()->addMinutes(ceil($duration/2));
                            $intermediateEnd = $intermediateStart->copy()->addMinutes($duration);
                            
                            if ($intermediateEnd <= $workEnd && !$this->isSlotOccupied($intermediateStart, $intermediateEnd, $schedule->user_id, $appointments)) {
                                $timeGroups[$intermediateStart->format('Y-m-d H:i:s')] = [
                                    'start' => $intermediateStart->toDateTimeString(),
                                    'end' => $intermediateEnd->toDateTimeString(),
                                    'employee_ids' => [$schedule->user_id],
                                    'service_id' => $serviceId
                                ];
                            }
                        }
    
                        $currentSlot->addMinutes(15); // Intervalos de 15 minutos para "now"
                    }
                } else {
                    // Para los demás casos, generar slots regulares sin superposición
                    while ($currentSlot->copy()->addMinutes($duration) <= $workEnd) {
                        $slotEnd = $currentSlot->copy()->addMinutes($duration);
                        $timeKey = $currentSlot->format('Y-m-d H:i:s');
    
                        if (!$this->isSlotOccupied($currentSlot, $slotEnd, $schedule->user_id, $appointments)) {
                            $timeGroups[$timeKey] = [
                                'start' => $currentSlot->toDateTimeString(),
                                'end' => $slotEnd->toDateTimeString(),
                                'employee_ids' => [$schedule->user_id],
                                'service_id' => $serviceId
                            ];
                        }
    
                        $currentSlot->addMinutes($duration); // Intervalos regulares basados en la duración
                    }
                }
            }
        }

        // Agregar los slots agrupados
        foreach ($timeGroups as $slot) {
            $slot['employee_ids'] = array_unique($slot['employee_ids']);
            $slot['service_id'] = $serviceId;
            $slots[] = $slot;
        }

        return $slots;
    }

    private function isSlotOccupied(Carbon $start, Carbon $end, int $employeeId, $appointments): bool
    {
        return $appointments->where('user_id', $employeeId)
            ->contains(function ($app) use ($start, $end) {
                return $start < Carbon::parse($app->end_date) && $end > Carbon::parse($app->start_date);
            });
    }

    private function addTakenSlotsInfo(array $slots, $appointments): array
    {
        // Crear un mapa de slots ocupados por tiempo
        $occupiedSlots = [];
        
        foreach ($appointments as $app) {
            $startTime = Carbon::parse($app->start_date);
            $endTime = Carbon::parse($app->end_date);
            $key = $startTime->format('Y-m-d H:i:s') . '_' . $endTime->format('Y-m-d H:i:s');
            
            if (!isset($occupiedSlots[$key])) {
                $occupiedSlots[$key] = [
                    'start' => $startTime->toDateTimeString(),
                    'end' => $endTime->toDateTimeString(),
                    'occupied_employee_ids' => [],
                    'service_id' => $app->service_id
                ];
            }
            
            $occupiedSlots[$key]['occupied_employee_ids'][] = $app->user_id;
        }
        
        // Agregar información de ocupación a los slots existentes
        foreach ($slots as &$slot) {
            $key = $slot['start'] . '_' . $slot['end'];
            
            if (isset($occupiedSlots[$key])) {
                $slot['occupied_employee_ids'] = $occupiedSlots[$key]['occupied_employee_ids'];
            } else {
                $slot['occupied_employee_ids'] = [];
            }
        }
        
        // Agregar slots completamente ocupados si no existen ya
        foreach ($occupiedSlots as $key => $occupiedSlot) {
            $exists = false;
            foreach ($slots as $slot) {
                if ($slot['start'] === $occupiedSlot['start'] && $slot['end'] === $occupiedSlot['end']) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $occupiedSlot['employee_ids'] = []; // No hay empleados disponibles
                $slots[] = $occupiedSlot;
            }
        }
        
        return $slots;
    }

    // Reemplazar el método anterior que no se usa
    private function addTakenSlots(array $slots, $appointments): array
    {
        return $this->addTakenSlotsInfo($slots, $appointments);
    }
}
