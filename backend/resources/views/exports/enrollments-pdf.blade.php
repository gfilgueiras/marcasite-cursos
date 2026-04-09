<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de inscrições — Marcasite</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
<h1>Inscrições em cursos</h1>
<p style="font-size:10px;color:#555;">Marcasite Cursos — gerado automaticamente</p>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Aluno</th>
        <th>E-mail</th>
        <th>Telefone</th>
        <th>Curso</th>
        <th>Pagamento</th>
        <th>Valor (centavos)</th>
        <th>Data da inscrição</th>
    </tr>
    </thead>
    <tbody>
    @foreach($enrollments as $e)
        <tr>
            <td>{{ $e->id }}</td>
            <td>{{ $e->student->name }}</td>
            <td>{{ $e->student->email }}</td>
            <td>{{ $e->student->phone }}</td>
            <td>{{ $e->course->name }}</td>
            <td>{{ $e->payment_status }}</td>
            <td>{{ $e->amount_cents }}</td>
            <td>{{ $e->enrolled_at?->format('Y-m-d H:i') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
