import axios from 'axios';
import { useAuthStore } from '../stores/authStore';

// URL API Backend Laravel 12 (Dapat disesuaikan via ENV atau Default localhost)
const API_BASE_URL = 'http://localhost:8000/api';

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 10000,
});

// Interceptor untuk menyisipkan Token Authorization Sanctum
api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Interceptor response handler
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().setToken(null);
    }
    return Promise.reject(error);
  }
);
