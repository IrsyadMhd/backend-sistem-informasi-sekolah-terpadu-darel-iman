import { api } from './api';

export interface AttendancePayload {
  student_id?: string;
  employee_id?: string;
  status: 'Hadir' | 'Sakit' | 'Izin' | 'Alpha';
  keterangan?: string;
  lat?: number;
  long?: number;
}

export interface TahfizhPayload {
  student_id: string;
  juz: number;
  surah: string;
  ayat_mulai: number;
  ayat_selesai: number;
  nilai: 'Mumtaz' | 'Jayyid Jiddan' | 'Jayyid' | 'Maqbul';
  catatan?: string;
}

export const mobileApiService = {
  // 1. Dashboard Ringkasan
  getDashboard: async () => {
    try {
      const response = await api.get('/dashboard');
      return response.data;
    } catch (error) {
      console.log('Error fetching dashboard, using fallback:', error);
      return null;
    }
  },

  // 2. Auth & Profile
  getProfile: async () => {
    const response = await api.get('/auth/profile');
    return response.data;
  },

  // 3. Presensi Absensi
  checkIn: async (payload: AttendancePayload) => {
    const response = await api.post('/attendance/checkin', payload);
    return response.data;
  },

  checkOut: async (payload: AttendancePayload) => {
    const response = await api.post('/attendance/checkout', payload);
    return response.data;
  },

  getAttendanceReport: async (params = {}) => {
    try {
      const response = await api.get('/attendance/report', { params });
      return response.data;
    } catch (error) {
      console.log('Error fetching attendance report:', error);
      return null;
    }
  },

  // 4. Setoran Tahfizh Al-Qur'an
  submitTahfizh: async (payload: TahfizhPayload) => {
    const response = await api.post('/tahfizh/store', payload);
    return response.data;
  },

  getTahfizhReport: async (params = {}) => {
    try {
      const response = await api.get('/tahfizh/report', { params });
      return response.data;
    } catch (error) {
      console.log('Error fetching tahfizh report:', error);
      return null;
    }
  },

  // 5. Data Siswa
  getStudents: async (params = {}) => {
    try {
      const response = await api.get('/students', { params });
      return response.data;
    } catch (error) {
      console.log('Error fetching students:', error);
      return null;
    }
  },
};
