<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Domaines PayDunya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .btn-group {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-danger {
            background: #ff5252;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-danger:hover {
            background: #ff2e2e;
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-edit:hover {
            background: #ffb300;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="url"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h2 {
            font-size: 20px;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #333;
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead {
            background: #f5f5f5;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .modal-content {
                max-width: 95%;
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }

            .actions {
                flex-direction: column;
            }

            .btn-edit, .btn-danger {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Gestion des Domaines PayDunya</h1>
            <p>Configurez et managez les domaines autorisés pour votre intégration PayDunya</p>
        </div>

        <div class="content">
            <div id="alerts"></div>

            <div class="section">
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="openAddModal()">
                        + Ajouter un domaine
                    </button>
                    <button class="btn btn-secondary" onclick="refreshDomains()">
                        🔄 Rafraîchir
                    </button>
                </div>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Chargement en cours...</p>
                </div>

                <div id="domainsContainer"></div>
            </div>
        </div>
    </div>

    <div class="modal" id="domainModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Ajouter un domaine</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>

            <form id="domainForm" onsubmit="handleFormSubmit(event)">
                <input type="hidden" id="domaine_id" name="domaine_id" value="">
                <input type="hidden" id="formAction" name="action" value="add">

                <div class="form-group">
                    <label for="domaine">Domaine *</label>
                    <input type="text" id="domaine" name="domaine" placeholder="exemple.com" required>
                </div>

                <div class="form-group">
                    <label for="partenaire_id">Partenaire *</label>
                    <select id="partenaire_id" name="partenaire_id" required>
                        <option value="">-- Sélectionner un partenaire --</option>
                    </select>
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="require_https" name="require_https" value="1" checked>
                        <label for="require_https" style="margin: 0;">HTTPS obligatoire</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_cors" name="allow_cors" value="1" checked>
                        <label for="allow_cors" style="margin: 0;">CORS autorisé</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="allow_actions" name="allow_actions" value="1" checked>
                        <label for="allow_actions" style="margin: 0;">Actions autorisées</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="actif" name="actif" value="1" checked>
                        <label for="actif" style="margin: 0;">Actif</label>
                    </div>
                </div>

                <div class="section-title" style="font-size: 16px; border: none; margin-top: 25px;">URLs de Callback</div>

                <div class="form-group">
                    <label for="callback_url">Callback URL</label>
                    <input type="url" id="callback_url" name="callback_url" placeholder="https://exemple.com/paydunya/callback.php">
                </div>

                <div class="form-group">
                    <label for="return_url">Return URL</label>
                    <input type="url" id="return_url" name="return_url" placeholder="https://exemple.com/paydunya/confirm.php">
                </div>

                <div class="form-group">
                    <label for="cancel_url">Cancel URL</label>
                    <input type="url" id="cancel_url" name="cancel_url" placeholder="https://exemple.com/paydunya/cancel.php">
                </div>

                <div class="btn-group" style="margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: 1;">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script src="./js/formulaireAddPrt.js"></script>
</body>
</html>