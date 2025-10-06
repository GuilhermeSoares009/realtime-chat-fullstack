const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

class ApiClient {
  constructor() {
    this.token = null;
    if (typeof window !== 'undefined') {
      this.token = localStorage.getItem('auth_token');
    }
  }

  setToken(token) {
    this.token = token;
    if (typeof window !== 'undefined') {
      localStorage.setItem('auth_token', token);
    }
  }

  clearToken() {
    this.token = null;
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
    }
  }

  getHeaders() {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    return headers;
  }

  async request(endpoint, options = {}) {
    const url = `${API_URL}${endpoint}`;
    const config = {
      ...options,
      headers: {
        ...this.getHeaders(),
        ...options.headers,
      },
    };

    try {
      const response = await fetch(url, config);
      
      if (response.status === 401) {
        this.clearToken();
        if (typeof window !== 'undefined') {
          window.location.href = '/';
        }
        throw new Error('Unauthorized');
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || `HTTP ${response.status}`);
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  async register(name, email, password, passwordConfirmation) {
    const data = await this.request('/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      }),
    });
    
    if (data.token) {
      this.setToken(data.token);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(data.user));
      }
    }
    
    return data;
  }

  async login(email, password) {
    const data = await this.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    
    if (data.token) {
      this.setToken(data.token);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(data.user));
      }
    }
    
    return data;
  }

  async logout() {
    try {
      await this.request('/auth/logout', { method: 'POST' });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      this.clearToken();
    }
  }

  async me() {
    const data = await this.request('/auth/me');
    if (typeof window !== 'undefined') {
      localStorage.setItem('user', JSON.stringify(data.user));
    }
    return data.user;
  }

  async getUsers() {
    const data = await this.request('/users/search?q=');
    return data.data || data;
  }

  async searchUsers(query) {
    const data = await this.request(`/users/search?q=${encodeURIComponent(query)}`);
    return data.data || data;
  }

  async getUser(userId) {
    const data = await this.request(`/users/${userId}`);
    return data.user;
  }

  async updateUser(updates) {
    const data = await this.request('/users/me', {
      method: 'PUT',
      body: JSON.stringify(updates),
    });
    
    if (typeof window !== 'undefined') {
      localStorage.setItem('user', JSON.stringify(data.user));
    }
    
    return data.user;
  }

  async getChats() {
    const data = await this.request('/chats');
    return data.data || data;
  }

  async getChat(chatId) {
    const data = await this.request(`/chats/${chatId}`);
    return data.chat;
  }

  async createDirectChat(userId) {
    const data = await this.request('/chats/direct', {
      method: 'POST',
      body: JSON.stringify({ user_id: userId }),
    });
    return data.chat;
  }

  async deleteChat(chatId) {
    await this.request(`/chats/${chatId}`, { method: 'DELETE' });
  }

  async markChatAsRead(chatId) {
    await this.request(`/chats/${chatId}/read`, { method: 'POST' });
  }

  async getMessages(chatId, page = 1) {
    const data = await this.request(`/chats/${chatId}/messages?page=${page}&per_page=50`);
    return data.data || data;
  }

  async sendMessage(chatId, content) {
    const data = await this.request(`/chats/${chatId}/messages`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    });
    return data.message;
  }

  async deleteMessage(chatId, messageId) {
    await this.request(`/chats/${chatId}/messages/${messageId}`, {
      method: 'DELETE',
    });
  }

  async sendTyping(chatId, isTyping) {
    await this.request(`/chats/${chatId}/typing`, {
      method: 'POST',
      body: JSON.stringify({ is_typing: isTyping }),
    });
  }
}

export const apiClient = new ApiClient();