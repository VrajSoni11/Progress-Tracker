// Global variables
let currentDate = '';
let currentDateDisplay = '';

// Open day modal
function openDayModal(date, displayDate) {
    currentDate = date;
    currentDateDisplay = displayDate;
    
    document.getElementById('modalTitle').textContent = displayDate;
    document.getElementById('dayModal').style.display = 'block';
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalTasksContent').style.display = 'none';
    
    loadDayTasks(date);
}

// Close day modal
function closeDayModal() {
    document.getElementById('dayModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('dayModal');
    if (event.target == modal) {
        closeDayModal();
    }
}

// Load tasks for a specific day
function loadDayTasks(date) {
    fetch(`api_tasks.php?action=get_day_tasks&date=${date}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalLoading').style.display = 'none';
            document.getElementById('modalTasksContent').style.display = 'block';
            renderDayTasks(data);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load tasks');
        });
}

// Render tasks in modal
function renderDayTasks(data) {
    const container = document.getElementById('modalTasksContent');
    
    let html = `
        <div style="margin-bottom: 25px;">
            <button onclick="showAddTaskForm()" class="btn-add-task">
                ➕ Add New Task
            </button>
        </div>
        
        <div id="addTaskForm" style="display:none; background:#f8f9fa; padding:20px; border-radius:10px; margin-bottom:20px;">
            <h3 style="margin-bottom:15px;">Add New Task</h3>
            <input type="text" id="newTaskTitle" placeholder="Task title" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; margin-bottom:10px; font-size:1rem;">
            <textarea id="newTaskDescription" placeholder="Description (optional)" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; margin-bottom:10px; resize:vertical; min-height:80px; font-size:1rem;"></textarea>
            <select id="newTaskCategory" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; margin-bottom:15px; font-size:1rem;">
                ${data.categories.map(cat => `<option value="${cat.id}">${cat.category_icon} ${cat.category_name}</option>`).join('')}
            </select>
            <div style="display:flex; gap:10px;">
                <button onclick="addTask()" style="flex:1; padding:12px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                    Add Task
                </button>
                <button onclick="hideAddTaskForm()" style="padding:12px 20px; background:#f0f0f0; border:none; border-radius:8px; cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    `;
    
    // Group tasks by category
    const tasksByCategory = {};
    data.tasks.forEach(task => {
        if (!tasksByCategory[task.category_name]) {
            tasksByCategory[task.category_name] = {
                icon: task.category_icon,
                color: task.category_color,
                tasks: []
            };
        }
        tasksByCategory[task.category_name].tasks.push(task);
    });
    
    // Render categories
    if (Object.keys(tasksByCategory).length === 0) {
        html += '<p style="text-align:center; color:#999; padding:40px; font-style:italic;">No tasks for this day. Click "Add New Task" to get started!</p>';
    } else {
        for (const [categoryName, categoryData] of Object.entries(tasksByCategory)) {
            html += `
                <div style="margin-bottom:25px;">
                    <div style="background:${categoryData.color}; color:#333; padding:12px 20px; border-radius:8px; font-weight:600; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.5rem;">${categoryData.icon}</span>
                        <span>${categoryName}</span>
                        <span style="margin-left:auto; background:rgba(255,255,255,0.7); padding:4px 12px; border-radius:20px; font-size:0.9rem;">
                            ${categoryData.tasks.filter(t => t.is_completed).length}/${categoryData.tasks.length}
                        </span>
                    </div>
            `;
            
            categoryData.tasks.forEach(task => {
                html += `
                    <div style="background:${task.is_completed ? '#e8f5e9' : '#f8f9fa'}; padding:15px; border-radius:8px; margin-bottom:8px; border-left:4px solid ${categoryData.color};">
                        <div style="display:flex; align-items:start; gap:12px;">
                            <input type="checkbox" ${task.is_completed ? 'checked' : ''} 
                                   onchange="toggleTask(${task.id})" 
                                   style="width:20px; height:20px; cursor:pointer; margin-top:2px; accent-color:#667eea;">
                            <div style="flex:1;">
                                <div style="font-weight:600; margin-bottom:5px; ${task.is_completed ? 'text-decoration:line-through; color:#999;' : ''}">
                                    ${task.task_title}
                                </div>
                                ${task.task_description ? `<div style="color:#666; font-size:0.9rem; margin-bottom:8px;">${task.task_description}</div>` : ''}
                                ${task.is_completed && task.completed_at ? `<div style="font-size:0.85rem; color:#4caf50;">✓ Completed at ${formatTime(task.completed_at)}</div>` : ''}
                            </div>
                            <button onclick="deleteTask(${task.id})" style="padding:8px 15px; background:#ff6b6b; color:white; border:none; border-radius:6px; cursor:pointer; font-size:0.85rem; transition:all 0.3s;" onmouseover="this.style.background='#ff5252'" onmouseout="this.style.background='#ff6b6b'">
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
    }
    
    container.innerHTML = html;
}

// Show add task form
function showAddTaskForm() {
    document.getElementById('addTaskForm').style.display = 'block';
    document.getElementById('newTaskTitle').focus();
}

// Hide add task form
function hideAddTaskForm() {
    document.getElementById('addTaskForm').style.display = 'none';
    document.getElementById('newTaskTitle').value = '';
    document.getElementById('newTaskDescription').value = '';
}

// Add new task
function addTask() {
    const title = document.getElementById('newTaskTitle').value.trim();
    const description = document.getElementById('newTaskDescription').value.trim();
    const categoryId = document.getElementById('newTaskCategory').value;
    
    if (!title) {
        alert('Please enter a task title');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_task');
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category_id', categoryId);
    formData.append('date', currentDate);
    
    fetch('api_tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload tasks in modal (keeps modal open)
            loadDayTasks(currentDate);
            hideAddTaskForm();
            
            // Update dashboard stats in background without closing modal
            updateDashboardStats();
        } else {
            alert(data.message || 'Failed to add task');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add task');
    });
}

// Toggle task completion
function toggleTask(taskId) {
    const formData = new FormData();
    formData.append('action', 'toggle_task');
    formData.append('task_id', taskId);
    
    fetch('api_tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload tasks in modal (keeps modal open)
            loadDayTasks(currentDate);
            
            // Update dashboard stats in background without closing modal
            updateDashboardStats();
        } else {
            alert(data.message || 'Failed to update task');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update task');
    });
}

// Delete task
function deleteTask(taskId) {
    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_task');
    formData.append('task_id', taskId);
    
    fetch('api_tasks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload tasks in modal (keeps modal open)
            loadDayTasks(currentDate);
            
            // Update dashboard stats in background without closing modal
            updateDashboardStats();
        } else {
            alert(data.message || 'Failed to delete task');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete task');
    });
}

// Format time
function formatTime(datetime) {
    const date = new Date(datetime);
    const hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 || 12;
    const minuteStr = minutes < 10 ? '0' + minutes : minutes;
    return `${hour12}:${minuteStr} ${ampm}`;
}

// Add button style
const style = document.createElement('style');
style.textContent = `
    .btn-add-task {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(102,126,234,0.3);
    }
    .btn-add-task:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102,126,234,0.4);
    }
`;
document.head.appendChild(style);

// Update dashboard stats without page reload
function updateDashboardStats() {
    // Fetch updated stats
    fetch('api_tasks.php?action=get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update progress circles
                updateProgressCircle('today', data.today_progress);
                updateProgressCircle('week', data.week_progress);
                updateProgressCircle('month', data.month_progress);
                
                // Update task counts
                updateTaskCounts('today', data.today_completed, data.today_total);
                updateTaskCounts('week', data.week_completed, data.week_total);
                updateTaskCounts('month', data.month_completed, data.month_total);
                
                // Update day cards on main dashboard
                updateDayCards();
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}

// Helper: Update progress circle
function updateProgressCircle(type, progress) {
    const circles = document.querySelectorAll('.stat-card .progress-ring-fill');
    if (circles.length >= 3) {
        const index = type === 'today' ? 0 : (type === 'week' ? 1 : 2);
        const circle = circles[index];
        if (circle) {
            circle.style.strokeDashoffset = `calc(220 - (220 * ${progress}) / 100)`;
        }
    }
    
    // Update percentage text
    const statValues = document.querySelectorAll('.stat-value');
    if (statValues.length >= 3) {
        const index = type === 'today' ? 0 : (type === 'week' ? 1 : 2);
        if (statValues[index]) {
            statValues[index].textContent = `${progress}%`;
        }
    }
}

// Helper: Update task counts
function updateTaskCounts(type, completed, total) {
    const statCards = document.querySelectorAll('.stat-card small');
    if (statCards.length >= 3) {
        const index = type === 'today' ? 0 : (type === 'week' ? 1 : 2);
        if (statCards[index]) {
            statCards[index].textContent = `${completed}/${total} tasks`;
        }
    }
}

// Helper: Update day cards in carousel
function updateDayCards() {
    // Reload the monthly calendar dots
    if (typeof populateMonthlyCalendar === 'function') {
        populateMonthlyCalendar();
    }
    
    // Note: Day cards will be updated on next page load
    // For real-time update, we'd need to fetch and re-render each card
    // But that's overkill - the stats update is what matters most
}
