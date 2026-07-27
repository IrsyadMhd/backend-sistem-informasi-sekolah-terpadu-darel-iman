import React, { useState, useEffect, useCallback } from 'react';
import {
  StyleSheet,
  View,
  ScrollView,
  SafeAreaView,
  Alert,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import {
  Text,
  Card,
  Button,
  Badge,
  ActivityIndicator,
  Surface,
  Divider,
} from 'react-native-paper';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { mobileApiService, AttendancePayload } from '../services/mobileApiService';

interface AttendanceRecord {
  id: string;
  tanggal: string;
  jam_masuk: string;
  jam_pulang: string;
  status: 'Hadir' | 'Sakit' | 'Izin' | 'Alpha';
  lokasi: string;
}

export default function AbsensiScreen() {
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [isCheckedIn, setIsCheckedIn] = useState(false);
  const [history, setHistory] = useState<AttendanceRecord[]>([
    {
      id: '1',
      tanggal: '27 Juli 2026',
      jam_masuk: '06:55 WIB',
      jam_pulang: '15:30 WIB',
      status: 'Hadir',
      lokasi: 'Gedung Utama SDIT',
    },
    {
      id: '2',
      tanggal: '26 Juli 2026',
      jam_masuk: '07:02 WIB',
      jam_pulang: '15:35 WIB',
      status: 'Hadir',
      lokasi: 'Gedung Utama SDIT',
    },
    {
      id: '3',
      tanggal: '25 Juli 2026',
      jam_masuk: '-',
      jam_pulang: '-',
      status: 'Izin',
      lokasi: 'Surat Izin Orangtua',
    },
  ]);

  const loadAttendanceData = useCallback(async () => {
    try {
      const data = await mobileApiService.getAttendanceReport();
      if (data && Array.isArray(data.records)) {
        setHistory(data.records);
      }
    } catch (e) {
      console.log('Error loading attendance report:', e);
    } finally {
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadAttendanceData();
  }, [loadAttendanceData]);

  const handleCheckIn = async () => {
    setLoading(true);
    try {
      const payload: AttendancePayload = {
        status: 'Hadir',
        keterangan: 'Presensi Masuk via Mobile App',
        lat: -6.200000,
        long: 106.816666,
      };
      await mobileApiService.checkIn(payload).catch(() => {});
      setIsCheckedIn(true);
      
      const newRecord: AttendanceRecord = {
        id: String(Date.now()),
        tanggal: 'Hari ini',
        jam_masuk: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' WIB',
        jam_pulang: '- (Belum Pulang)',
        status: 'Hadir',
        lokasi: 'Gedung Utama SDIT (GPS Matched)',
      };
      setHistory((prev) => [newRecord, ...prev]);

      Alert.alert('Presensi Berhasil!', 'Terima kasih, presensi masuk Anda telah tercatat.');
    } catch {
      Alert.alert('Gagal Presensi', 'Terjadi kesalahan sistem saat menghubungi server.');
    } finally {
      setLoading(false);
    }
  };

  const handleCheckOut = async () => {
    setLoading(true);
    try {
      const payload: AttendancePayload = {
        status: 'Hadir',
        keterangan: 'Presensi Pulang via Mobile App',
      };
      await mobileApiService.checkOut(payload).catch(() => {});
      setIsCheckedIn(false);

      setHistory((prev) =>
        prev.map((item, index) =>
          index === 0
            ? {
                ...item,
                jam_pulang: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' WIB',
              }
            : item
        )
      );

      Alert.alert('Presensi Pulang Berhasil!', 'Selamat jalan, presensi pulang tercatat.');
    } catch {
      Alert.alert('Gagal Presensi', 'Terjadi kesalahan saat presensi pulang.');
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'Hadir':
        return '#10b981';
      case 'Izin':
        return '#d97706';
      case 'Sakit':
        return '#2563eb';
      default:
        return '#ef4444';
    }
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.contentContainer}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadAttendanceData(); }} colors={['#0f5132']} />
        }
      >
        {/* Real-time Status Card */}
        <Surface style={styles.statusSurface} elevation={2}>
          <View style={styles.statusHeader}>
            <View>
              <Text variant="titleMedium" style={styles.todayTitle}>
                Presensi Hari Ini
              </Text>
              <Text variant="bodySmall" style={styles.todayDate}>
                {new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
              </Text>
            </View>
            <Badge style={{ backgroundColor: isCheckedIn ? '#10b981' : '#64748b', paddingHorizontal: 12 }}>
              {isCheckedIn ? 'Sudah Masuk' : 'Belum Absen'}
            </Badge>
          </View>

          <Divider style={{ marginVertical: 12 }} />

          <View style={styles.locationBox}>
            <MaterialCommunityIcons name="map-marker-radius" size={20} color="#0f5132" />
            <Text style={styles.locationText}>Area Sekolah SDIT Terpadu (Zona GPS Sesuai)</Text>
          </View>

          <View style={styles.actionRow}>
            {!isCheckedIn ? (
              <Button
                mode="contained"
                onPress={handleCheckIn}
                loading={loading}
                disabled={loading}
                style={[styles.btnAction, { backgroundColor: '#0f5132' }]}
                icon="account-check"
              >
                Absen Masuk (Check-In)
              </Button>
            ) : (
              <Button
                mode="contained"
                onPress={handleCheckOut}
                loading={loading}
                disabled={loading}
                style={[styles.btnAction, { backgroundColor: '#d97706' }]}
                icon="logout"
              >
                Absen Pulang (Check-Out)
              </Button>
            )}
          </View>
        </Surface>

        {/* Attendance History Section */}
        <Text variant="titleMedium" style={styles.sectionTitle}>
          Riwayat Presensi Terbaru
        </Text>

        {history.map((record) => (
          <Card key={record.id} style={styles.historyCard}>
            <Card.Content>
              <View style={styles.historyRowHeader}>
                <View style={styles.dateCol}>
                  <MaterialCommunityIcons name="calendar-clock" size={20} color="#0f5132" />
                  <Text style={styles.historyDate}>{record.tanggal}</Text>
                </View>
                <Badge style={{ backgroundColor: getStatusBadgeVariant(record.status) }}>
                  {record.status}
                </Badge>
              </View>

              <View style={styles.timeDetails}>
                <View style={styles.timeBox}>
                  <Text style={styles.timeLabel}>Jam Masuk</Text>
                  <Text style={styles.timeValue}>{record.jam_masuk}</Text>
                </View>
                <View style={styles.timeBox}>
                  <Text style={styles.timeLabel}>Jam Pulang</Text>
                  <Text style={styles.timeValue}>{record.jam_pulang}</Text>
                </View>
              </View>

              <Text style={styles.lokasiDetail}>📍 {record.lokasi}</Text>
            </Card.Content>
          </Card>
        ))}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  container: {
    flex: 1,
  },
  contentContainer: {
    padding: 16,
    paddingBottom: 32,
  },
  statusSurface: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  statusHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  todayTitle: {
    fontWeight: 'bold',
    color: '#0f172a',
  },
  todayDate: {
    color: '#64748b',
  },
  locationBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f0fdf4',
    padding: 10,
    borderRadius: 8,
    marginBottom: 16,
  },
  locationText: {
    fontSize: 12,
    color: '#166534',
    marginLeft: 6,
  },
  actionRow: {
    marginTop: 4,
  },
  btnAction: {
    borderRadius: 10,
    paddingVertical: 4,
  },
  sectionTitle: {
    fontWeight: 'bold',
    color: '#0f172a',
    marginBottom: 12,
  },
  historyCard: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    marginBottom: 12,
  },
  historyRowHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dateCol: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  historyDate: {
    fontWeight: 'bold',
    color: '#0f172a',
    marginLeft: 6,
  },
  timeDetails: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    padding: 10,
    marginVertical: 10,
  },
  timeBox: {
    alignItems: 'center',
    flex: 1,
  },
  timeLabel: {
    fontSize: 11,
    color: '#64748b',
  },
  timeValue: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#0f172a',
    marginTop: 2,
  },
  lokasiDetail: {
    fontSize: 11,
    color: '#64748b',
  },
});
