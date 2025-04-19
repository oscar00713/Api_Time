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
                'include_taken' => 'boolean',
                'appointment_duration_minutes' => 'nullable' // Modified to accept any type
            ]);

            // Convert appointment_duration_minutes to array format if it's a string
            if (isset($validated['appointment_duration_minutes']) && !is_array($validated['appointment_duration_minutes'])) {
                // If it's a single value, apply it to all services
                $duration = (int)$validated['appointment_duration_minutes'];
                $durationArray = [];

                foreach ($validated['service_id'] as $serviceId) {
                    $durationArray[$serviceId] = $duration;
                }

                $validated['appointment_duration_minutes'] = $durationArray;
            }

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
            $totalDuration = $this->getTotalDuration($query, $validated['service_id'], $validated['appointment_duration_minutes'] ?? null);
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

    private function getTotalDuration($query, array $serviceIds, ?array $customDurations = null): array
    {
        $durations = $query->table('services')
            ->whereIn('id', $serviceIds)
            ->pluck('appointment_duration_minutes', 'id')
            ->toArray();

        // Override with custom durations if provided
        if ($customDurations) {
            foreach ($serviceIds as $serviceId) {
                if (isset($customDurations[$serviceId]) && $customDurations[$serviceId] > 0) {
                    $durations[$serviceId] = $customDurations[$serviceId];
                }
            }
        }

        return $durations;
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

    private function generateTimeSlots($query, $employees, array $durations, array $validated)
    {
        $period = $this->getDateRange($validated);
        $employeeIds = $employees->pluck('id');

        $timezone = config('app.timezone');
        $startDate = Carbon::parse($period['start'])->startOfDay()->format('Y-m-d H:i:s');
        $endDate = Carbon::parse($period['end'])->addDays(14)->endOfDay()->format('Y-m-d H:i:s');

        $existingAppointments = $query->table('appointments')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', '!=', 4) // Ignorar turnos cancelados
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get(['start_date', 'end_date', 'employee_id as user_id', 'service_id']);

        $blockedAppointments = $query->table('block_appointments')
            ->where(function ($q) use ($startDate, $endDate, $employeeIds) {
                $q->whereBetween('datetime_start', [$startDate, $endDate])
                    ->whereIn('employee_id', $employeeIds);
            })
            ->get(['id', 'datetime_start', 'employee_id', 'service_id']);

        $schedules = $this->getEmployeeSchedules($query, $employeeIds, $validated['service_id']);

        $allSlots = [];
        foreach ($validated['service_id'] as $serviceId) {
            $duration = $durations[$serviceId];
            $serviceSchedules = $schedules->where('service_id', $serviceId);

            $slots = $this->calculateAvailableSlots($period, $duration, $serviceSchedules, $existingAppointments, $validated, $serviceId);
            $allSlots = array_merge($allSlots, $slots);
        }

        // Only include taken slots if include_taken is true
        if (!empty($validated['include_taken'])) {
            $allSlots = $this->addTakenSlotsInfo($allSlots, $existingAppointments, $blockedAppointments);
        }

        usort($allSlots, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $allSlots;
    }

    private function getDateRange(array $validated): array
    {
        // Use the timezone already set in the middleware
        // $timezone = config('app.timezone');
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
            'morning', 'in the morning' => $now->copy()->setTime(6, 0),
            'afternoon', 'in the afternoon' => $now->copy()->setTime(12, 0),
            'night', 'in the night' => $now->copy()->setTime(18, 0),
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
            'morning', 'in the morning' => $now->copy()->setTime(12, 0),
            'afternoon', 'in the afternoon' => $now->copy()->setTime(18, 0),
            'night', 'in the night' => $now->copy()->setTime(23, 59),
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
                'is_out_of_schedule' => true // Siempre marcamos el primer slot como fuera de horario
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

                // Generar slots regulares durante el día laboral
                $slotStart = $workStart->copy();

                // Si es hoy y estamos en modo "now", comenzar desde la hora actual
                if ($date->isToday() && $validated['dayAndTime'] === 'now' && $now->gt($slotStart)) {
                    // Ajustamos para que el segundo slot comience en el siguiente intervalo regular
                    // después del primer slot especial
                    if ($firstSlotAdded) {
                        $firstSlotEndTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['end']);
                        // Encontrar el siguiente intervalo regular después del primer slot
                        $slotStart = $workStart->copy();
                        while ($slotStart < $firstSlotEndTime) {
                            $slotStart->addMinutes($duration);
                        }
                    } else {
                        $slotStart = $now->copy()->ceil($duration);
                    }
                }

                while ($slotStart->copy()->addMinutes($duration) <= $workEnd) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);

                    // Verificar que no se solape con el primer slot especial
                    $skipThisSlot = false;
                    if ($validated['dayAndTime'] === 'now' && $firstSlotAdded && $date->isToday()) {
                        $firstSlotStartTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['start']);
                        $firstSlotEndTime = Carbon::parse($timeGroups[array_key_first($timeGroups)]['end']);

                        if (
                            $slotStart->eq($firstSlotStartTime) ||
                            ($slotStart < $firstSlotEndTime && $slotEnd > $firstSlotStartTime)
                        ) {
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
        return $appointments->where('user_id', $employeeId)->where('status', '!=', 4)
            ->contains(function ($app) use ($start, $end) {
                return $start < Carbon::parse($app->end_date) && $end > Carbon::parse($app->start_date);
            });
    }

    private function addTakenSlotsInfo(array $slots, $appointments, $blockedAppointments = null): array
    {
        // Crear un mapa de slots ocupados por tiempo
        $occupiedSlots = $this->mapOccupiedSlots($appointments);

        // Inicializar arrays de ocupación en todos los slots
        $slots = $this->initializeOccupationArrays($slots);

        // Procesar citas bloqueadas
        if ($blockedAppointments && count($blockedAppointments) > 0) {
            // Mapear bloqueos a slots específicos con duración exacta
            $blockedEmployeeMap = $this->mapBlockedEmployees($blockedAppointments, $slots);

            // Aplicar los bloqueos a los slots existentes
            $slots = $this->applyBlocksToSlots($slots, $blockedEmployeeMap);

            // Añadir bloqueos a los slots ocupados
            $occupiedSlots = $this->addBlockedSlotsToOccupied($blockedAppointments, $occupiedSlots);
        }

        // Combinar slots disponibles con ocupados
        return $this->combineAvailableAndOccupiedSlots($slots, $occupiedSlots);
    }

    /**
     * Mapea las citas existentes a un array de slots ocupados
     */
    private function mapOccupiedSlots($appointments): array
    {
        $occupiedSlots = [];

        foreach ($appointments as $app) {
            $startTime = Carbon::parse($app->start_date);
            $endTime = Carbon::parse($app->end_date);
            $key = $this->generateSlotKey($startTime, $endTime);

            if (!isset($occupiedSlots[$key])) {
                $occupiedSlots[$key] = [
                    'start' => $startTime->toDateTimeString(),
                    'end' => $endTime->toDateTimeString(),
                    'occupied_employee_ids' => [],
                    'blocked_employee_ids' => [],
                    'service_id' => $app->service_id,
                    'time_label' => 'occupied'
                ];
            }

            $occupiedSlots[$key]['occupied_employee_ids'][] = $app->user_id;
        }

        return $occupiedSlots;
    }

    /**
     * Genera una clave única para un slot basado en su tiempo de inicio y fin
     */
    private function generateSlotKey(Carbon $start, Carbon $end): string
    {
        return $start->format('Y-m-d H:i:s') . '_' . $end->format('Y-m-d H:i:s');
    }

    /**
     * Mapea los empleados bloqueados a los slots correspondientes
     * Corrige el problema de bloquear slots adyacentes
     */
    private function mapBlockedEmployees($blockedAppointments, array $slots): array
    {
        $blockedEmployeeMap = [];

        foreach ($blockedAppointments as $block) {
            $blockStartTime = Carbon::parse($block->datetime_start);

            // Para cada slot, verificamos si coincide con el tiempo de bloqueo
            foreach ($slots as $slot) {
                $slotStart = Carbon::parse($slot['start']);
                $slotEnd = Carbon::parse($slot['end']);

                // Verificamos si el bloqueo coincide con este slot
                // Comparamos solo la fecha y hora sin segundos para mayor flexibilidad
                if ($blockStartTime->eq($slotStart)) {
                    $slotKey = $this->generateSlotKey($slotStart, $slotEnd);
                    if (!isset($blockedEmployeeMap[$slotKey])) {
                        $blockedEmployeeMap[$slotKey] = [];
                    }

                    // Añadimos el ID del empleado bloqueado al mapa
                    $blockedEmployeeMap[$slotKey][] = (int)$block->employee_id;
                }
            }
        }

        return $blockedEmployeeMap;
    }

    /**
     * Aplica la información de bloqueos a los slots disponibles
     */
    private function applyBlocksToSlots(array $slots, array $blockedEmployeeMap): array
    {
        foreach ($slots as $index => $slot) {
            $slotStart = Carbon::parse($slot['start']);
            $slotEnd = Carbon::parse($slot['end']);
            $slotKey = $this->generateSlotKey($slotStart, $slotEnd);

            if (isset($blockedEmployeeMap[$slotKey])) {
                // Asegúrate de que $blockedEmployeeMap[$slotKey] es un array antes de usar array_filter
                if (is_array($blockedEmployeeMap[$slotKey])) {
                    // Filtrar los bloqueos por service_id
                    $blockedIds = array_filter($blockedEmployeeMap[$slotKey], function ($blocked) use ($slot) {
                        return is_array($blocked) && $blocked['service_id'] === $slot['service_id'];
                    });

                    // Inicializar blocked_employee_ids si no existe
                    if (!isset($slots[$index]['blocked_employee_ids'])) {
                        $slots[$index]['blocked_employee_ids'] = [];
                    }

                    // Añadir los empleados bloqueados
                    $slots[$index]['blocked_employee_ids'] = array_values(array_unique(
                        array_merge($slots[$index]['blocked_employee_ids'], $blockedIds)
                    ));
                }
            }
        }

        return $slots;
    }

    /**
     * Inicializa los arrays de ocupación en todos los slots
     */
    private function initializeOccupationArrays(array $slots): array
    {
        foreach ($slots as &$slot) {
            // Inicializar arrays si no existen
            if (!isset($slot['occupied_employee_ids'])) {
                $slot['occupied_employee_ids'] = [];
            }
            if (!isset($slot['blocked_employee_ids'])) {
                $slot['blocked_employee_ids'] = [];
            }

            // Asegurarse de que employee_ids sea un array numérico
            if (isset($slot['employee_ids'])) {
                $slot['employee_ids'] = array_values(array_unique($slot['employee_ids']));
            } else {
                $slot['employee_ids'] = [];
            }
        }

        return $slots;
    }

    /**
     * Combina los slots disponibles con los ocupados
     */
    private function combineAvailableAndOccupiedSlots(array $slots, array $occupiedSlots): array
    {
        $finalSlots = [];
        $processedKeys = [];

        // Primero procesamos los slots disponibles
        foreach ($slots as $slot) {
            $slotStart = Carbon::parse($slot['start']);
            $slotEnd = Carbon::parse($slot['end']);
            $added = false;

            // Buscar si este slot se solapa con algún slot ocupado
            foreach ($occupiedSlots as $key => $occupiedSlot) {
                if (isset($processedKeys[$key])) continue;

                $occStart = Carbon::parse($occupiedSlot['start']);
                $occEnd = Carbon::parse($occupiedSlot['end']);

                // Si hay solapamiento exacto o parcial
                if (($slotStart->eq($occStart) && $slotEnd->eq($occEnd)) ||
                    ($slotStart < $occEnd && $slotEnd > $occStart)
                ) {
                    // Si es un bloqueo, combinar la información
                    if ($occupiedSlot['time_label'] === 'blocked') {
                        // Combinar los empleados bloqueados
                        $blockedIds = array_values(array_unique(
                            array_merge($slot['blocked_employee_ids'], $occupiedSlot['blocked_employee_ids'])
                        ));

                        // Crear un slot combinado
                        $combinedSlot = [
                            'start' => $slot['start'],
                            'end' => $slot['end'],
                            'employee_ids' => $slot['employee_ids'],
                            'service_id' => $slot['service_id'],
                            'is_out_of_schedule' => $slot['is_out_of_schedule'] ?? false,
                            'time_label' => $slot['time_label'],
                            'occupied_employee_ids' => $slot['occupied_employee_ids'],
                            'blocked_employee_ids' => $blockedIds
                        ];

                        $finalSlots[] = $combinedSlot;
                        $processedKeys[$key] = true;
                        $added = true;
                        break;
                    }
                }
            }

            // Si no se combinó con ningún slot ocupado, añadirlo tal cual
            if (!$added) {
                $finalSlots[] = $slot;
            }
        }

        // Añadir los slots ocupados que no se hayan procesado
        $finalSlots = $this->addRemainingOccupiedSlots($finalSlots, $occupiedSlots, $processedKeys);

        return $finalSlots;
    }

    /**
     * Añade los slots ocupados restantes que no se hayan procesado
     */
    private function addRemainingOccupiedSlots(array $finalSlots, array $occupiedSlots, array $processedKeys): array
    {
        foreach ($occupiedSlots as $key => $occupiedSlot) {
            if (!isset($processedKeys[$key])) {
                // Verificar si este slot ocupado se solapa con alguno de los slots finales
                $occStart = Carbon::parse($occupiedSlot['start']);
                $occEnd = Carbon::parse($occupiedSlot['end']);
                $exists = false;

                foreach ($finalSlots as $finalSlot) {
                    $finalStart = Carbon::parse($finalSlot['start']);
                    $finalEnd = Carbon::parse($finalSlot['end']);

                    // Si hay solapamiento exacto o parcial
                    if (($finalStart->eq($occStart) && $finalEnd->eq($occEnd)) ||
                        ($finalStart < $occEnd && $finalEnd > $occStart)
                    ) {
                        $exists = true;
                        break;
                    }
                }

                // Solo añadir si no existe un slot que se solape
                if (!$exists) {
                    // Asegurarse de que tiene todos los campos necesarios
                    if (!isset($occupiedSlot['employee_ids'])) {
                        $occupiedSlot['employee_ids'] = [];
                    }
                    if (!isset($occupiedSlot['is_out_of_schedule'])) {
                        $occupiedSlot['is_out_of_schedule'] = false;
                    }

                    $finalSlots[] = $occupiedSlot;
                }
            }
        }

        return $finalSlots;
    }

    // Reemplazar el método anterior que no se usa
    private function addTakenSlots(array $slots, $appointments, $blockedAppointments = null): array
    {
        return $this->addTakenSlotsInfo($slots, $appointments, $blockedAppointments);
    }

    /**
     * Añade los bloqueos a la lista de slots ocupados
     */
    private function addBlockedSlotsToOccupied($blockedAppointments, array $occupiedSlots): array
    {
        foreach ($blockedAppointments as $block) {
            $startTime = Carbon::parse($block->datetime_start);
            // Obtenemos la duración del servicio o usamos una duración estándar
            $endTime = $startTime->copy()->addMinutes(30); // Usamos 30 minutos como duración estándar para bloqueos
            $key = $this->generateSlotKey($startTime, $endTime);

            if (!isset($occupiedSlots[$key])) {
                $occupiedSlots[$key] = [
                    'start' => $startTime->toDateTimeString(),
                    'end' => $endTime->toDateTimeString(),
                    'occupied_employee_ids' => [],
                    'blocked_employee_ids' => [],
                    'service_id' => $block->service_id ?? 0,
                    'time_label' => 'blocked'
                ];
            }

            // Aseguramos que employee_id exista antes de añadirlo
            if (isset($block->employee_id)) {
                $occupiedSlots[$key]['blocked_employee_ids'][] = $block->employee_id;
            }
        }

        return $occupiedSlots;
    }
}
