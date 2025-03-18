<?php

namespace App\Http\Controllers\api\db;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Companies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

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

            return response()->json([
                'suggestions' => $timeSlots,
                'time_label' => $validated['dayAndTime'] // Añadimos la etiqueta de tiempo a la respuesta
            ]);
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

        // Corregimos la consulta para usar start_date en lugar de date
        $existingAppointments = $query->table('appointments')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('start_date', [$period['start'], $period['end']])  // Cambiado de 'date' a 'start_date'
            ->get(['start_date', 'end_date', 'employee_id as user_id', 'service_id']);

        // Get blocked appointments
        $blockedAppointments = $query->table('block_appointments')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('datetime_start', [$period['start'], $period['end']])
            ->get(['datetime_start', 'employee_id', 'service_id']);

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
            $allSlots = $this->addTakenSlotsInfo($allSlots, $existingAppointments, $blockedAppointments);
        }

        // Ordenar todos los slots por fecha
        usort($allSlots, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $allSlots;
    }

    private function getDateRange(array $validated): array
    {
        // Use the timezone already set in the middleware
        $now = Carbon::now();

        // Determinar fecha de inicio según el parámetro dayAndTime
        $start = match ($validated['dayAndTime']) {
            'now' => $now->copy(),
            'about_now' => $now->copy()->addMinutes(30),
            '1hour' => $now->copy(),
            'tomorrow' => $now->copy()->addDay()->startOfDay(),
            'next_week' => $now->copy()->next($now->dayOfWeek)->startOfDay(), // Mismo día de la semana próxima
            'next_month' => $now->copy()->addMonth()->startOfDay(),
            '3months' => $now->copy()->addMonths(3)->startOfDay(),
            '6months' => $now->copy()->addMonths(6)->startOfDay(),
            'morning' => $now->copy()->setTime(6, 0),
            'afternoon' => $now->copy()->setTime(12, 0),
            'night' => $now->copy()->setTime(18, 0),
            default => Carbon::parse($validated['calendar_date'] . ' ' . $validated['calendar_time'])
        };

        // Determinar fecha de fin según el parámetro dayAndTime
        $end = match ($validated['dayAndTime']) {
            'now' => $now->copy()->endOfDay(),
            'about_now' => $now->copy()->endOfDay(),
            '1hour' => $now->copy()->addHours(3),
            'tomorrow' => $now->copy()->addDay()->endOfDay(),
            'next_week' => $now->copy()->next($now->dayOfWeek)->endOfDay(), // Mismo día de la semana próxima
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
        $foundWorkingDay = false;
        $firstSlotAdded = false; // Variable para controlar si ya se añadió el primer slot
    
        // Para el caso "now", siempre añadimos un primer slot con la hora actual
        if ($validated['dayAndTime'] === 'now' && !$firstSlotAdded) {
            // Primer slot basado en la hora actual
            $firstSlotStart = $now->copy()->ceil($duration);
            $firstSlotEnd = $firstSlotStart->copy()->addMinutes($duration);
    
            // Añadimos el primer slot sin importar si está dentro del horario laboral
            $timeGroups[$firstSlotStart->format('Y-m-d H:i:s')] = [
                'start' => $firstSlotStart->toDateTimeString(),
                'end' => $firstSlotEnd->toDateTimeString(),
                'employee_ids' => $schedules->pluck('user_id')->unique()->toArray(),
                'service_id' => $serviceId,
                'is_out_of_schedule' => true // Marcamos como fuera de horario por defecto
            ];
            $firstSlotAdded = true;
        }
    
        // Crear un período extendido para buscar el próximo día laborable si es necesario
        $extendedPeriod = CarbonPeriod::create(
            $period['start'],
            $period['start']->copy()->addDays(14) // Extender 2 semanas para buscar días laborables
        );
    
        foreach ($extendedPeriod as $date) {
            $dayHasSlots = false;
    
            foreach ($schedules as $schedule) {
                $dayName = strtolower($date->format('l'));
                if (!$schedule->$dayName) continue;
    
                // Si encontramos un día laborable y no es el primer día del período original,
                // y estamos en el caso de 'now', 'about_now', o '1hour', ajustamos el período
                if (
                    !$foundWorkingDay &&
                    !$date->isSameDay($period['start']) &&
                    in_array($validated['dayAndTime'], ['now', 'about_now', '1hour'])
                ) {
                    // Reemplazar el período original con uno que comience en este día laborable
                    $period['start'] = $date->copy()->startOfDay();
                    $period['end'] = $date->copy()->endOfDay();
                    $period['period'] = CarbonPeriod::create($period['start'], $period['end']);
                    $foundWorkingDay = true;
                }
    
                $workStart = Carbon::parse($schedule->hora_inicio)->setDateFrom($date);
                $workEnd = Carbon::parse($schedule->hora_fim)->setDateFrom($date);
    
                // Verificar si el primer slot está dentro del horario laboral
                if ($validated['dayAndTime'] === 'now' && $firstSlotAdded && $date->isToday()) {
                    $firstSlotStartTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['start']);
                    $firstSlotEndTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['end']);
                    
                    // Actualizar is_out_of_schedule si el slot está dentro del horario
                    if ($firstSlotStartTime >= $workStart && $firstSlotEndTime <= $workEnd) {
                        $timeGroups[array_key_first($timeGroups)]['is_out_of_schedule'] = false;
                    }
                }
    
                // Generar slots regulares durante el día laboral
                $slotStart = $workStart->copy();
    
                // Si es hoy y estamos en modo "now", comenzar desde la hora actual
                if ($date->isToday() && $validated['dayAndTime'] === 'now' && $now->gt($slotStart)) {
                    $slotStart = $now->copy()->ceil($duration);
                }
    
                while ($slotStart->copy()->addMinutes($duration) <= $workEnd) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);
                    
                    // Verificar que no se solape con el primer slot especial
                    $skipThisSlot = false;
                    if ($validated['dayAndTime'] === 'now' && $firstSlotAdded && $date->isToday()) {
                        $firstSlotStartTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['start']);
                        $firstSlotEndTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['end']);
                        
                        if ($slotStart->eq($firstSlotStartTime)) {
                            $skipThisSlot = true;
                        }
                    }
                    
                    if (!$skipThisSlot && !$this->isSlotOccupied($slotStart, $slotEnd, $schedule->user_id, $appointments)) {
                        $key = $slotStart->format('Y-m-d H:i:s');
                        
                        if (!isset($timeGroups[$key])) {
                            $timeGroups[$key] = [
                                'start' => $slotStart->toDateTimeString(),
                                'end' => $slotEnd->toDateTimeString(),
                                'employee_ids' => [],
                                'service_id' => $serviceId,
                                'is_out_of_schedule' => false
                            ];
                        }
                        
                        $timeGroups[$key]['employee_ids'][] = $schedule->user_id;
                        $dayHasSlots = true;
                    }
                    
                    $slotStart->addMinutes($duration);
                }
    
                // Si este día tiene slots disponibles, marcarlo
                if ($dayHasSlots) {
                    $foundWorkingDay = true;
                }
            }
    
            // Si encontramos slots para este día y estamos buscando el próximo día laborable,
            // y no estamos en el período original, terminamos la búsqueda extendida
            if (
                $dayHasSlots && $foundWorkingDay && !$date->isSameDay($period['start']) &&
                in_array($validated['dayAndTime'], ['now', 'about_now', '1hour'])
            ) {
                break;
            }
    
            // Si ya procesamos todos los días del período original y no encontramos slots,
            // seguimos buscando en el período extendido
            if ($date->gte($period['end']) && !$foundWorkingDay) {
                continue;
            }
    
            // Si estamos fuera del período original y no estamos buscando el próximo día laborable,
            // terminamos
            if ($date->gt($period['end']) && !in_array($validated['dayAndTime'], ['now', 'about_now', '1hour'])) {
                break;
            }
        }
    
        // Agregar los slots agrupados
        foreach ($timeGroups as $slot) {
            // Convertir employee_ids a un array numérico simple
            $employeeIds = array_values(array_unique($slot['employee_ids']));
            $slot['employee_ids'] = $employeeIds;
            $slot['time_label'] = $validated['dayAndTime']; // Añadimos la etiqueta de tiempo a cada slot
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

    private function addTakenSlotsInfo(array $slots, $appointments, $blockedAppointments = null): array
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
                    'blocked_employee_ids' => [], // Add blocked_employee_ids field
                    'service_id' => $app->service_id,
                    'time_label' => 'occupied' // Etiqueta para slots ocupados
                ];
            }

            $occupiedSlots[$key]['occupied_employee_ids'][] = $app->user_id;
        }

        // Add blocked appointments to the map
        if ($blockedAppointments) {
            foreach ($blockedAppointments as $block) {
                $startTime = Carbon::parse($block->datetime_start);
                // For blocked appointments, we'll use a 30-minute duration by default
                // You might want to adjust this based on your business logic
                $endTime = $startTime->copy()->addMinutes(30);
                $key = $startTime->format('Y-m-d H:i:s') . '_' . $endTime->format('Y-m-d H:i:s');

                if (!isset($occupiedSlots[$key])) {
                    $occupiedSlots[$key] = [
                        'start' => $startTime->toDateTimeString(),
                        'end' => $endTime->toDateTimeString(),
                        'occupied_employee_ids' => [],
                        'blocked_employee_ids' => [],
                        'service_id' => $block->service_id,
                        'time_label' => 'blocked' // Etiqueta para slots bloqueados
                    ];
                }

                $occupiedSlots[$key]['blocked_employee_ids'][] = $block->employee_id;
            }
        }

        // Agregar información de ocupación a los slots existentes
        foreach ($slots as &$slot) {
            $key = $slot['start'] . '_' . $slot['end'];

            if (isset($occupiedSlots[$key])) {
                $slot['occupied_employee_ids'] = array_values(array_unique($occupiedSlots[$key]['occupied_employee_ids'] ?? []));
                $slot['blocked_employee_ids'] = array_values(array_unique($occupiedSlots[$key]['blocked_employee_ids'] ?? []));
            } else {
                $slot['occupied_employee_ids'] = [];
                $slot['blocked_employee_ids'] = []; // Add empty blocked_employee_ids array
            }
            
            // Asegurarse de que employee_ids sea un array numérico
            if (isset($slot['employee_ids'])) {
                $slot['employee_ids'] = array_values(array_unique($slot['employee_ids']));
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
                $occupiedSlot['occupied_employee_ids'] = array_values(array_unique($occupiedSlot['occupied_employee_ids']));
                $occupiedSlot['blocked_employee_ids'] = array_values(array_unique($occupiedSlot['blocked_employee_ids']));
                $slots[] = $occupiedSlot;
            }
        }

        return $slots;
    }

    // Reemplazar el método anterior que no se usa
    private function addTakenSlots(array $slots, $appointments, $blockedAppointments = null): array
    {
        return $this->addTakenSlotsInfo($slots, $appointments, $blockedAppointments);
    }
}
