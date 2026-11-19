<?php

// Carrega a conexão ($pdo) para as telas que incluem apenas este model
require_once __DIR__ . '/../config/conexao.php';

class Game
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Todos os jogos, do mais novo para o mais antigo
    public function listar()
    {
        $stmt = $this->pdo->query("SELECT * FROM games ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Um jogo pelo id (ou false se não existir)
    public function buscarPorId($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function cadastrar($titulo, $descricao, $preco, $estoque, $categoria, $imagem)
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO games (titulo, descricao, preco, estoque, categoria, imagem)
             VALUES (:titulo, :descricao, :preco, :estoque, :categoria, :imagem)"
        );

        return $stmt->execute([
            ':titulo'    => $titulo,
            ':descricao' => $descricao,
            ':preco'     => $preco,
            ':estoque'   => $estoque,
            ':categoria' => $categoria,
            ':imagem'    => $imagem,
        ]);
    }

    public function editar($id, $titulo, $descricao, $preco, $estoque, $categoria, $imagem)
    {
        $stmt = $this->pdo->prepare(
            "UPDATE games
             SET titulo = :titulo, descricao = :descricao, preco = :preco,
                 estoque = :estoque, categoria = :categoria, imagem = :imagem
             WHERE id = :id"
        );

        return $stmt->execute([
            ':titulo'    => $titulo,
            ':descricao' => $descricao,
            ':preco'     => $preco,
            ':estoque'   => $estoque,
            ':categoria' => $categoria,
            ':imagem'    => $imagem,
            ':id'        => $id,
        ]);
    }

    public function remover($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM games WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
