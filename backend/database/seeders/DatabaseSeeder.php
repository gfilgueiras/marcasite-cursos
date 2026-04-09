<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@marcasite.local'],
            [
                'name' => 'Administrador Marcasite',
                'password' => 'password',
                'role' => 'admin',
                'active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->tokens()->where('name', 'dev-seed')->delete();
        $admin->createToken('dev-seed');

        User::factory()->count(50)->create();

        $cursos = [
            [
                'name' => 'Fundamentos de Lógica de Programação',
                'description' => 'Aprenda estruturas de decisão, repetição e resolução de problemas para iniciar na área de tecnologia.',
                'price_cents' => 129_00,
            ],
            [
                'name' => 'JavaScript para Iniciantes',
                'description' => 'Domine variáveis, funções, arrays e objetos com foco em aplicações web modernas.',
                'price_cents' => 149_00,
            ],
            [
                'name' => 'TypeScript na Prática',
                'description' => 'Aumente a qualidade do seu código JavaScript com tipagem estática e boas práticas.',
                'price_cents' => 179_00,
            ],
            [
                'name' => 'HTML e CSS Moderno',
                'description' => 'Construa interfaces responsivas com Flexbox, Grid e componentes reutilizáveis.',
                'price_cents' => 139_00,
            ],
            [
                'name' => 'Vue 3 do Zero ao Deploy',
                'description' => 'Crie aplicações com Composition API, roteamento e consumo de APIs REST.',
                'price_cents' => 229_00,
            ],
            [
                'name' => 'React Essencial',
                'description' => 'Desenvolva interfaces performáticas com componentes, hooks e gerenciamento de estado.',
                'price_cents' => 239_00,
            ],
            [
                'name' => 'Node.js e APIs REST',
                'description' => 'Implemente APIs escaláveis com Express, autenticação e validação de dados.',
                'price_cents' => 249_00,
            ],
            [
                'name' => 'PHP 8 para Web',
                'description' => 'Aprenda recursos modernos do PHP para criar aplicações robustas no backend.',
                'price_cents' => 199_00,
            ],
            [
                'name' => 'Laravel Profissional',
                'description' => 'Desenvolva sistemas completos com Eloquent, filas, eventos e testes automatizados.',
                'price_cents' => 289_00,
            ],
            [
                'name' => 'Banco de Dados SQL',
                'description' => 'Modelagem relacional, consultas avançadas e otimização de desempenho com MySQL.',
                'price_cents' => 189_00,
            ],
            [
                'name' => 'NoSQL com MongoDB',
                'description' => 'Entenda documentos, índices e agregações para aplicações de alta escalabilidade.',
                'price_cents' => 209_00,
            ],
            [
                'name' => 'Git e GitHub para Times',
                'description' => 'Versionamento de código, fluxo de branches e colaboração eficiente em equipe.',
                'price_cents' => 119_00,
            ],
            [
                'name' => 'Testes Automatizados para Web',
                'description' => 'Aplique testes unitários e de integração para reduzir bugs em produção.',
                'price_cents' => 219_00,
            ],
            [
                'name' => 'Docker para Desenvolvedores',
                'description' => 'Containerize aplicações e padronize ambientes de desenvolvimento e produção.',
                'price_cents' => 199_00,
            ],
            [
                'name' => 'CI/CD com GitHub Actions',
                'description' => 'Automatize build, testes e deploy com pipelines modernas de entrega contínua.',
                'price_cents' => 229_00,
            ],
            [
                'name' => 'AWS para Iniciantes',
                'description' => 'Conheça serviços essenciais da nuvem para hospedar aplicações e bancos de dados.',
                'price_cents' => 269_00,
            ],
            [
                'name' => 'Linux para Desenvolvimento',
                'description' => 'Comandos essenciais, shell scripting e administração básica para devs.',
                'price_cents' => 149_00,
            ],
            [
                'name' => 'Segurança em Aplicações Web',
                'description' => 'Previna vulnerabilidades como SQL Injection, XSS e falhas de autenticação.',
                'price_cents' => 259_00,
            ],
            [
                'name' => 'Arquitetura de Software',
                'description' => 'Aprenda padrões arquiteturais, separação de responsabilidades e escalabilidade.',
                'price_cents' => 279_00,
            ],
            [
                'name' => 'Microserviços na Prática',
                'description' => 'Projete serviços independentes, comunicação assíncrona e observabilidade.',
                'price_cents' => 299_00,
            ],
            [
                'name' => 'Engenharia de Prompt com IA',
                'description' => 'Crie prompts eficientes para produtividade, desenvolvimento e automação.',
                'price_cents' => 189_00,
            ],
            [
                'name' => 'Data Analytics com Python',
                'description' => 'Analise dados com Pandas, visualizações e geração de insights para negócios.',
                'price_cents' => 239_00,
            ],
            [
                'name' => 'Introdução a Machine Learning',
                'description' => 'Entenda os principais algoritmos de aprendizado supervisionado e não supervisionado.',
                'price_cents' => 319_00,
            ],
        ];

        $courseIds = [];
        foreach ($cursos as $idx => $curso) {
            $start = Carbon::now()->subDays(7 + $idx);
            $end = (clone $start)->addMonths(4);

            $course = Course::query()->updateOrCreate(
                ['name' => $curso['name']],
                [
                    'description' => $curso['description'],
                    'banner_path' => null,
                    'price_cents' => $curso['price_cents'],
                    'currency' => 'brl',
                    'active' => true,
                    'enrollment_starts_at' => $start->toDateString(),
                    'enrollment_ends_at' => $end->toDateString(),
                    'max_seats' => 30 + ($idx * 3) % 120,
                ]
            );
            $courseIds[] = $course->id;
        }

        foreach (range(1, 8) as $n) {
            $idx = 22 + $n;
            $start = Carbon::now()->subDays(7 + $idx);
            $end = (clone $start)->addMonths(4);
            $course = Course::query()->updateOrCreate(
                ['name' => "Curso demonstração #{$n}"],
                [
                    'description' => "Conteúdo de exemplo para demonstração do catálogo (demonstração {$n}).",
                    'banner_path' => null,
                    'price_cents' => 10_000 + ($n * 1_500),
                    'currency' => 'brl',
                    'active' => true,
                    'enrollment_starts_at' => $start->toDateString(),
                    'enrollment_ends_at' => $end->toDateString(),
                    'max_seats' => 40 + ($n * 11) % 90,
                ]
            );
            $courseIds[] = $course->id;
        }

        if (Student::query()->count() === 0) {
            $students = Student::factory()->count(40)->create();

            $statuses = [
                PaymentStatus::Paid->value,
                PaymentStatus::Paid->value,
                PaymentStatus::Pending->value,
                PaymentStatus::Failed->value,
            ];

            foreach (range(1, 32) as $i) {
                $courseId = $courseIds[array_rand($courseIds)];
                $student = $students->random();
                $status = $statuses[$i % count($statuses)];
                $course = Course::query()->findOrFail($courseId);

                $enrollment = Enrollment::query()->create([
                    'student_id' => $student->id,
                    'course_id' => $courseId,
                    'payment_status' => $status,
                    'amount_cents' => $course->price_cents,
                    'currency' => 'brl',
                    'enrolled_at' => now()->subDays(rand(0, 20)),
                    'stripe_checkout_session_id' => $status === PaymentStatus::Paid->value ? 'cs_seed_'.$i.'_'.uniqid() : null,
                ]);

                if ($status === PaymentStatus::Paid->value) {
                    Payment::query()->create([
                        'enrollment_id' => $enrollment->id,
                        'provider' => 'stripe',
                        'provider_payment_id' => 'cs_seed_pay_'.$enrollment->id,
                        'amount_cents' => $enrollment->amount_cents,
                        'status' => PaymentStatus::Paid->value,
                        'raw_payload' => ['seed' => true, 'enrollment_id' => $enrollment->id],
                    ]);
                }
            }

            DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => 'evt_dev_seed_fixed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
