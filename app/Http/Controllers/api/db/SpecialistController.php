<?php

namespace App\Http\Controllers\api\db;

use Carbon\Carbon;
use App\Models\Companies;
use Illuminate\Support\Str;
use App\Mail\InvitationMail;
use Illuminate\Http\Request;
use App\Mail\PostCreatedMail;
use App\Models\Users_Invitations;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\LimitCheckService;

class SpecialistController extends Controller
{
    protected $limitCheckService;
    public $allRoles = [
        'manage_services',
        'manage_users',
        'manage_cash',
        'view_client_history',
        'create_client_history',
        'cancel_client_history',
        'appointments_own',
        'appointments_other',
        'appointments_self_assign',
        'appointments_self_others',
        'appointments_cancel_own',
        'appointments_cancel_others',
        'appointments_reschedule_own',
        'appointments_reschedule_others',
        'history_view',
        'history_create',
        'history_edit',
        'history_delete',
        'employees_create',
        'employees_edit',
        'employees_delete',
        'manage_register',
        'edit_money_own',
        'edit_money_any',
        'audit_register',
        'view_reports',
        'generate_reports',
        'delete_reports',
        'stock_add',
        'stock_edit'
    ];
    public function __construct(LimitCheckService $limitCheckService)
    {
        $this->limitCheckService = $limitCheckService;
    }
    /**
     * Display a listing of the resource.
     */
    //TODO:cambio porque se ara todo en la tabla users ya no existira la tabla specialistas
    public function index(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        $allRoles = $this->allRoles; // Usar la propiedad de clase
        $forAppointment = $request->query('for_appointment', false);

        // Si es para crear citas, solo mostrar usuarios activos de la tabla users
        if ($forAppointment) {
            $query = DB::connection($dbConnection)
                ->table('users')
                ->leftJoin('roles', 'users.id', '=', 'roles.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.user_type',
                    'users.active',
                    'users.phone',
                    'users.registration',
                    'users.fixed_salary',
                    'users.badge_color',
                    'users.fixed_salary_frecuency',
                    DB::raw('NULL::BOOLEAN as manage_salary'),
                    DB::raw('NULL::BOOLEAN as use_room'),
                    DB::raw('json_agg(roles.*)::json as roles')
                )
                ->where('users.active', true) // Solo usuarios activos
                ->groupBy('users.id');

            // Aplicar filtros de búsqueda si existen
            if ($request->has('filter') && isset($request->get('filter')['all'])) {
                $searchTerm = '%' . $request->get('filter')['all'] . '%';
                $query->whereAny(
                    [
                        'users.name',
                        'users.email',
                    ],
                    'ilike',
                    $searchTerm
                );
            }

            // Ordenamiento
            $query->orderBy('users.name', 'asc');

            // Paginación
            $specialists = $query->paginate(
                $request->query('perPage', 15),
                ['*'],
                'page',
                $request->query('page', 1)
            );

            return response()->json($specialists);
        }
        // Subquery para users (con filtros aplicados)
        $usersQuery = DB::connection($dbConnection)
            ->table('users')
            ->leftJoin('roles', 'users.id', '=', 'roles.user_id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.user_type',
                'users.active',
                'users.phone',
                'users.registration',
                'users.fixed_salary',
                'users.badge_color',
                'users.fixed_salary_frecuency',
                DB::raw('NULL::BOOLEAN as manage_salary'),
                DB::raw('NULL::BOOLEAN as use_room'),
                DB::raw('json_agg(roles.*)::json as roles')
            )
            ->groupBy('users.id');

        // Subquery para users_temp (con filtros aplicados)
        $usersTempQuery = DB::connection($dbConnection)
            ->table('users_temp')
            ->select(
                'users_temp.id',
                'users_temp.name',
                'users_temp.email',
                'users_temp.user_type',
                'users_temp.active',
                'users_temp.phone',
                'users_temp.registration',
                'users_temp.fixed_salary',
                'users_temp.badge_color',
                'users_temp.fixed_salary_frecuency',
                'users_temp.manage_salary',
                'users_temp.use_room',
                DB::raw("
                (
                    SELECT jsonb_build_array(jsonb_object_agg(role, true))
                    FROM jsonb_array_elements_text(users_temp.roles) AS role
                    WHERE role IN ('" . implode("','", $allRoles) . "')
                )::json as roles
            ")
            );

        // Aplicar filtros a ambas subconsultas
        if ($request->has('filter')) {
            $filters = $request->get('filter');

            // Filtro 'active'
            if (isset($filters['active'])) {
                $usersQuery->where('users.active', $filters['active']);
                $usersTempQuery->where('users_temp.active', $filters['active']);
            }

            // Filtro 'all' (búsqueda general)
            if (isset($filters['all'])) {
                $searchTerm = '%' . $filters['all'] . '%';
                $usersQuery->whereAny(
                    [
                        'users.name',
                        'users.fixed_salary',
                        'users.email',
                    ],
                    'ilike',
                    $searchTerm
                );
                $usersTempQuery->whereAny(
                    [
                        'users_temp.name',
                        'users_temp.fixed_salary',
                        'users_temp.email',
                    ],
                    'ilike',
                    $searchTerm
                );
            }
        }

        // Unir ambas consultas
        $mergedQuery = $usersQuery->unionAll($usersTempQuery);

        // Crear consulta principal a partir de la unión
        $query = DB::connection($dbConnection)
            ->query()
            ->fromSub($mergedQuery, 'merged_users');

        // Ordenamiento global (si es necesario)
        if ($request->has('sort')) {
            $sort = $request->get('sort');
            if (in_array($sort, ['name', 'fixed_salary'])) {
                $query->orderBy($sort);
            }
        }

        // Paginación
        $specialists = $query->paginate(
            $request->query(
                'perPage',
                15
            ),
            ['*'],
            'page',
            $request->query('page', 1)
        );

        return response()->json($specialists);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $dbConnection = $request->get('db_connection');
        // Consultar datos en la conexión dinámica
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'fixed_salary' => 'nullable|numeric',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:255',
            'badge_color' => 'nullable|string|max:50',
            'fixed_salary_frecuency' => 'nullable|string|max:50',
            'manage_salary' => 'nullable|boolean',
            'registration' => 'nullable|string|max:50',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|in:' . implode(',', $this->allRoles),
            'use_room' => 'nullable|boolean',
            'user_type' => 'string|max:50',
        ]);
        if (!$this->limitCheckService->canAddEmployee($dbConnection)) {
            return response()->json([
                'error' => 'No se pueden crear más empleados. Se ha alcanzado el límite máximo.'
            ], 403);
        }
        if (empty($validatedData['email']) || ($validatedData['user_type'] == 'fake')) {

            $user = DB::connection($dbConnection)->table('users')->insertGetId([
                'name' => $validatedData['name'],
                'fixed_salary' => $validatedData['fixed_salary'] ?? 0,
                'user_type' => 'fake',
                'hash' => Str::random(15),
                'email' => strtolower($validatedData['email']) ?? null,
                'badge_color' => $validatedData['badge_color'],
                'active' => true,
                'phone' => $validatedData['phone'] ?? '',
                'fixed_salary_frecuency' => $validatedData['fixed_salary_frecuency'] ?? 'monthly',
            ]);

            return response()->json(['message' => 'ok'], 201);
        }


        $user = $request->user;
        if (empty($validatedData['email'])) {
            return response()->json(['error' => 'email_required'], 409);
        }
        $email = strtolower($validatedData['email']);

        DB::beginTransaction();
        try {
            // Guardar los datos en la tabla `specialists` usando la conexión dinámica
            // Guardar los datos en el modelo `UserInvitaciones` en la conexión predeterminada
            //Obtener el id de la compañía
            $companyID = Companies::where('db_name', $request->header('d'))->first();
            // dd($companyID["name"]);

            //verificar si ya hay una invitacion para el mismo email
            $invitation = Users_Invitations::where('email', $validatedData['email'])->where('company_id', $companyID["id"])->first();
            if ($invitation) {
                return response()->json(['error' => 'repeated'], 409);
            }


            $existeUser = DB::connection('dynamic_pgsql')->table('users')->where('email', $validatedData['email'])->first();
            if (!$existeUser) {

                //mandar invirtacion si en caso de no existir en la user_invitations
                $tokenHash = Str::random(15);
                $invitationUrl = url('https://app.timeboard.live/accept-invitation/' . $tokenHash);
                $sender = $user["name"];
                $senderEmail = $user["email"];
                $companyName = $companyID["name"];


                $userId = DB::connection($dbConnection)->table('users_temp')->insertGetId([
                    'name' => $validatedData['name'],
                    'fixed_salary' => $validatedData['fixed_salary'] ?? 0,
                    'user_type' => 'invitation',
                    'email' => strtolower($validatedData['email']),
                    'badge_color' => $validatedData['badge_color'],
                    'active' => false,
                    'phone' => $validatedData['phone'] ?? '',
                    'fixed_salary_frecuency' => $validatedData['fixed_salary_frecuency'] ?? 'monthly',
                    'manage_salary' => $validatedData['manage_salary'] ?? false,
                    'use_room' => $validatedData['use_room'] ?? false,
                    'registration' => $validatedData['registration'] ?? '',
                    'roles' =>  json_encode(
                        $validatedData['permissions']
                    ) ?? json_encode([])
                ]);
                // Construir datos de roles
                // $rolesFromRequest = $validatedData['permissions'] ?? [];
                // $rolesData = ['user_id' => $userId];

                // foreach ($this->allRoles as $role) {
                //     $rolesData[$role] = in_array($role, $rolesFromRequest);
                // }

                // Crear los roles del usuario con el ID obtenido directamente
                // DB::connection($dbConnection)->table('roles')->insert($rolesData);

                //verificar si el email ya esta en la tabla de invitaciones
                //crear solo si tiene menos de 3 intentos
                if (!$invitation) {
                    Users_Invitations::create([
                        'email' => $email,
                        'company_id' => $companyID["id"],
                        'attempts' => 1,
                        'sender_id' => $user["id"],
                        'sender_name' => $user["name"],
                        'sender_email' => $user["email"],
                        'last_attempt_at' => now(),
                        'invitationtoken' => $tokenHash,
                        'accepted' => null,
                        'expiration' => Carbon::now()->addDays(7),
                        'updated_at' => now(),
                    ]);
                    Mail::to([$email])->send(new InvitationMail($invitationUrl, $sender, $senderEmail, $companyName));
                } else {
                    //aumentar el attempts siempre que sea menor a 3 sino mostrar error
                    if ($invitation->attempts < 3) {
                        Mail::to([$email])->send(new InvitationMail($invitationUrl, $sender, $senderEmail, $companyName));
                        $invitation->attempts++;
                        $invitation->last_attempt_at = now();
                        $invitation->invitationtoken = $tokenHash;
                        $invitation->accepted = null;
                        $invitation->save();
                    }
                    //si el intento es mayor a 3 mostrar error
                    else {
                        return response()->json(['error' => 'error'], 409);
                    }
                }
            } else {
                return response()->json(['error' => 'repeated'], 409);
            }

            DB::commit();
            return response()->json(['message' => 'ok'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'error' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //actulizar la tabla specialists
        $dbConnection = $request->get('db_connection');
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'fixed_salary' => 'nullable|numeric',
            'badge_color' => 'nullable|string|max:50',
            'fixed_salary_frecuency' => 'nullable|string|max:50',
            'manage_salary' => 'nullable|boolean',
            'user_type' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:255',
            'active' => 'nullable|boolean', // Asegúrate de validar 'active'
            'permissions' => 'nullable|array', // Validación para permisos
            'permissions.*' => 'nullable|in:' . implode(',', $this->allRoles),
            'use_room' => 'nullable|boolean',
            'registration' => 'nullable|string|max:50'
        ]);



        //validar para cambiar en la tabla users_temp
        if ($validatedData['user_type'] == 'invitation') {

            DB::connection($dbConnection)->table('users_temp')->where('id', $id)->update([
                'fixed_salary' => $validatedData['fixed_salary'] ?? 0,
                'badge_color' => $validatedData['badge_color'],
                'active' => $validatedData['active'],
                'phone' => $validatedData['phone'] ?? '',
                'manage_salary' => $validatedData['manage_salary'] ?? false,
                'fixed_salary_frecuency' => $validatedData['fixed_salary_frecuency'] ?? 'monthly',
                'use_room' => $validatedData['use_room'] ?? false,
                'registration' => $validatedData['registration'] ?? '',
                'roles' => json_encode(
                    $validatedData['permissions']
                ) ?? json_encode([])
            ]);
        } elseif ($validatedData['user_type'] == 'user') {

            //TODO: no se debe de cambiar el name ni email
            DB::connection($dbConnection)->table('users')->where('id', $id)->update([
                'fixed_salary' => $validatedData['fixed_salary'] ?? 0,
                'badge_color' => $validatedData['badge_color'],
                'active' => $validatedData['active'],
                'phone' => $validatedData['phone'] ?? '',
                'manage_salary' => $validatedData['manage_salary'] ?? false,
                'fixed_salary_frecuency' => $validatedData['fixed_salary_frecuency'] ?? 'monthly',
                'use_room' => $validatedData['use_room'] ?? false,
                'registration' => $validatedData['registration'] ?? ''
            ]);
            // Procesar permisos
            $rolesFromRequest = $validatedData['permissions'] ?? [];
            $rolesData = [];
            foreach ($this->allRoles as $role) {
                $rolesData[$role] = in_array($role, $rolesFromRequest);
            }

            // Actualizar o insertar permisos en la tabla roles
            DB::connection($dbConnection)->table('roles')->updateOrInsert(
                ['user_id' => $id],
                $rolesData
            );
        } elseif ($validatedData['user_type'] == 'fake') {

            //guardar datos en la tabla userTemp
            DB::connection($dbConnection)->table('users')->where('id', $id)->update([
                'name' => $validatedData['name'],
                'fixed_salary' => $validatedData['fixed_salary'] ?? 0,
                'badge_color' => $validatedData['badge_color'],
                'active' => $validatedData['active'],
                'phone' => $validatedData['phone'] ?? '',
                'manage_salary' => $validatedData['manage_salary'] ?? false,
                'fixed_salary_frecuency' => $validatedData['fixed_salary_frecuency'] ?? 'monthly',
                'use_room' => $validatedData['use_room'] ?? false,
                'registration' => $validatedData['registration'] ?? '',

            ]);
        } else {
            return response()->json(['error' => 'error'], 409);
        }
        return response()->json(['message' => 'ok'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        //delete the specialist
        $user_type = $request->user_type;
        $dbConnection = $request->get('db_connection');

        if ($user_type == 'invitation') {
            DB::connection($dbConnection)->table('users_temp')->where('id', $id)->delete();
        }

        // elseif ($user_type == 'fake') {
        //     DB::connection($dbConnection)->table('users_temp')->where('id', $id)->delete();
        // }
        else {
            DB::connection($dbConnection)->table('users')->where('id', $id)->delete();
            //delete roles of the specialist
            DB::connection($dbConnection)->table('roles')->where('user_id', $id)->delete();
        }
        return response()->json(['message' => 'ok'], 201);
    }
}
