const API_URL = '../../gestionDePaiement/admin_domains_api.php';

async function refreshDomains() {
    showLoading(true);
    try {
        const response = await fetch(`${API_URL}?action=list`);
        const result = await response.json();

        if (result.success) {
            renderDomains(result.data);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Erreur: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

async function loadPartners() {
    try {
        const response = await fetch(`${API_URL}?action=partners`);
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('partenaire_id');
            select.innerHTML = '<option value="">-- Sélectionner un partenaire --</option>';
            result.data.forEach(partner => {
                const option = document.createElement('option');
                option.value = partner.id;
                option.textContent = partner.nom + (partner.actif ? '' : ' (inactif)');
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erreur chargement partenaires:', error);
    }
}

function renderDomains(domains) {
    const container = document.getElementById('domainsContainer');

    if (!domains || domains.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">🌐</div>
                <h3>Aucun domaine configuré</h3>
                <p>Cliquez sur "Ajouter un domaine" pour commencer</p>
            </div>
        `;
        return;
    }

    let html = `
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>Partenaire</th>
                        <th>HTTPS</th>
                        <th>CORS</th>
                        <th>Actions</th>
                        <th>Statut</th>
                        <th>Opérations</th>
                    </tr>
                </thead>
                <tbody>
    `;

    domains.forEach(domain => {
        html += `
            <tr>
                <td><strong>${escapeHtml(domain.domaine)}</strong></td>
                <td>${escapeHtml(domain.partenaire_nom || 'N/A')}</td>
                <td>${domain.require_https ? '✓' : '✗'}</td>
                <td>${domain.allow_cors ? '✓' : '✗'}</td>
                <td>${domain.allow_actions ? '✓' : '✗'}</td>
                <td>
                    <span class="status-badge ${domain.actif ? 'status-active' : 'status-inactive'}">
                        ${domain.actif ? 'Actif' : 'Inactif'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <button class="btn btn-edit" onclick="editDomain(${domain.id})">Éditer</button>
                        <button class="btn btn-danger" onclick="deleteDomain(${domain.id})">Supprimer</button>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

async function editDomain(id) {
    try {
        const response = await fetch(`${API_URL}?action=list`);
        const result = await response.json();

        if (result.success) {
            const domain = result.data.find(d => d.id === id);
            if (domain) {
                populateForm(domain);
                document.getElementById('modalTitle').textContent = 'Éditer le domaine';
                document.getElementById('formAction').value = 'update';
                openModal();
            }
        }
    } catch (error) {
        showAlert('Erreur: ' + error.message, 'error');
    }
}

async function deleteDomain(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce domaine ?')) {
        return;
    }

    showLoading(true);
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('domaine_id', id);

        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showAlert(result.message, 'success');
            refreshDomains();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Erreur: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function populateForm(domain) {
    document.getElementById('domaine_id').value = domain.id;
    document.getElementById('domaine').value = domain.domaine;
    document.getElementById('partenaire_id').value = domain.partenaire_id || '';
    document.getElementById('require_https').checked = domain.require_https === 1;
    document.getElementById('allow_cors').checked = domain.allow_cors === 1;
    document.getElementById('allow_actions').checked = domain.allow_actions === 1;
    document.getElementById('actif').checked = domain.actif === 1;
    document.getElementById('callback_url').value = domain.callback_url || '';
    document.getElementById('return_url').value = domain.return_url || '';
    document.getElementById('cancel_url').value = domain.cancel_url || '';
}

function clearForm() {
    document.getElementById('domaine_id').value = '';
    document.getElementById('domainForm').reset();
    document.getElementById('require_https').checked = true;
    document.getElementById('allow_cors').checked = true;
    document.getElementById('allow_actions').checked = true;
    document.getElementById('actif').checked = true;
    document.getElementById('formAction').value = 'add';
}

async function handleFormSubmit(event) {
    event.preventDefault();

    const formData = new FormData(document.getElementById('domainForm'));

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showAlert(result.message, 'success');
            closeModal();
            refreshDomains();
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        showAlert('Erreur: ' + error.message, 'error');
    }
}

function openAddModal() {
    clearForm();
    document.getElementById('modalTitle').textContent = 'Ajouter un domaine';
    document.getElementById('formAction').value = 'add';
    openModal();
}

function openModal() {
    document.getElementById('domainModal').classList.add('active');
}

function closeModal() {
    document.getElementById('domainModal').classList.remove('active');
}

function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

function showAlert(message, type = 'info') {
    const alertsContainer = document.getElementById('alerts');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    alertsContainer.appendChild(alert);

    setTimeout(() => alert.remove(), 4000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.addEventListener('load', () => {
    loadPartners();
    refreshDomains();
});
