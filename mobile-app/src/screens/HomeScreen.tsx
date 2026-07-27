import React, { useEffect, useState, useCallback } from 'react';
import {
  StyleSheet,
  View,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import {
  Text,
  Card,
  Avatar,
  Badge,
  ActivityIndicator,
  Surface,
  Divider,
} from 'react-native-paper';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { mobileApiService } from '../services/mobileApiService';

interface DashboardStats {
  total_siswa: number;
  presensi_hari_ini: number;
  setoran_tahfizh: number;
  pengumuman: Array<{ id: string; judul: string; tanggal: string; isi: string }>;
}

export default function HomeScreen() {
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [stats, setStats] = useState<DashboardStats>({
    total_siswa: 1250,
    presensi_hari_ini: 98,
    setoran_tahfizh: 42,
    pengumuman: [
      {
        id: '1',
        judul: 'Ujian Munaqosyah Tahfizh Semester Ganjil',
        tanggal: '27 Juli 2026',
        isi: 'Pelaksanaan Munaqosyah Juz 29 dan 30 akan diselenggarakan pada hari Sabtu mendatang.',
      },
      {
        id: '2',
        judul: 'Penerimaan Peserta Didik Baru (PPDB) T.A 2026/2027',
        tanggal: '25 Juli 2026',
        isi: 'Pendaftaran gelombang 1 resmi dibuka untuk unit SDIT dan SMPIT.',
      },
    ],
  });

  const loadDashboardData = useCallback(async () => {
    try {
      const data = await mobileApiService.getDashboard();
      if (data) {
        setStats((prev) => ({
          ...prev,
          total_siswa: data.total_siswa || prev.total_siswa,
          presensi_hari_ini: data.presensi_hari_ini || prev.presensi_hari_ini,
          setoran_tahfizh: data.setoran_tahfizh || prev.setoran_tahfizh,
        }));
      }
    } catch (e) {
      console.log('Error loading home data:', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadDashboardData();
  }, [loadDashboardData]);

  const onRefresh = () => {
    setRefreshing(true);
    loadDashboardData();
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar backgroundColor="#0f5132" barStyle="light-content" />
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.contentContainer}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#0f5132']} />
        }
      >
        {/* Header Profile Section */}
        <Surface style={styles.headerSurface} elevation={2}>
          <View style={styles.profileRow}>
            <Avatar.Text
              size={48}
              label="AZ"
              style={{ backgroundColor: '#10b981' }}
              color="#ffffff"
            />
            <View style={styles.profileInfo}>
              <Text variant="titleMedium" style={styles.userName}>
                Ahmad Zaki Al-Faruq
              </Text>
              <Text variant="bodySmall" style={styles.userRole}>
                Wali Murid • SDIT & SMPIT Islam Terpadu
              </Text>
            </View>
            <TouchableOpacity style={styles.notificationBtn}>
              <MaterialCommunityIcons name="bell-outline" size={24} color="#0f5132" />
            </TouchableOpacity>
          </View>
        </Surface>

        {/* Islamic Greeting Banner */}
        <Card style={styles.bannerCard}>
          <Card.Content style={styles.bannerContent}>
            <View style={styles.bannerTextCol}>
              <Text style={styles.arabicGreeting}>Assalamu'alaikum wr. wb.</Text>
              <Text style={styles.bannerSubtext}>
                Selamat datang di Aplikasi SIMS Terpadu Sekolah Islam.
              </Text>
            </View>
            <MaterialCommunityIcons name="mosque" size={42} color="#10b981" />
          </Card.Content>
        </Card>

        {/* Stat Cards Grid */}
        <Text variant="titleMedium" style={styles.sectionTitle}>
          Ringkasan Sekolah
        </Text>

        {loading ? (
          <ActivityIndicator style={{ marginVertical: 20 }} color="#0f5132" />
        ) : (
          <View style={styles.statsGrid}>
            <Card style={styles.statCard}>
              <Card.Content style={styles.statCardInner}>
                <MaterialCommunityIcons name="account-group" size={28} color="#0f5132" />
                <Text style={styles.statNumber}>{stats.total_siswa}</Text>
                <Text style={styles.statLabel}>Total Siswa</Text>
              </Card.Content>
            </Card>

            <Card style={styles.statCard}>
              <Card.Content style={styles.statCardInner}>
                <MaterialCommunityIcons name="clipboard-check" size={28} color="#10b981" />
                <Text style={styles.statNumber}>{stats.presensi_hari_ini}%</Text>
                <Text style={styles.statLabel}>Presensi Hari Ini</Text>
              </Card.Content>
            </Card>

            <Card style={styles.statCard}>
              <Card.Content style={styles.statCardInner}>
                <MaterialCommunityIcons name="book-open-page-variant" size={28} color="#d97706" />
                <Text style={styles.statNumber}>{stats.setoran_tahfizh}</Text>
                <Text style={styles.statLabel}>Setoran Tahfizh</Text>
              </Card.Content>
            </Card>
          </View>
        )}

        {/* Quick Menu */}
        <Text variant="titleMedium" style={styles.sectionTitle}>
          Akses Cepat
        </Text>
        <Surface style={styles.quickMenuSurface} elevation={1}>
          <View style={styles.quickMenuRow}>
            <TouchableOpacity style={styles.quickMenuItem}>
              <View style={[styles.iconCircle, { backgroundColor: '#ecfdf5' }]}>
                <MaterialCommunityIcons name="qrcode-scan" size={24} color="#0f5132" />
              </View>
              <Text style={styles.quickMenuText}>Absen QR</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.quickMenuItem}>
              <View style={[styles.iconCircle, { backgroundColor: '#fef3c7' }]}>
                <MaterialCommunityIcons name="book-open-page-variant" size={24} color="#d97706" />
              </View>
              <Text style={styles.quickMenuText}>Hafalan</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.quickMenuItem}>
              <View style={[styles.iconCircle, { backgroundColor: '#eff6ff' }]}>
                <MaterialCommunityIcons name="file-document-outline" size={24} color="#2563eb" />
              </View>
              <Text style={styles.quickMenuText}>Rapor</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.quickMenuItem}>
              <View style={[styles.iconCircle, { backgroundColor: '#f3e8ff' }]}>
                <MaterialCommunityIcons name="cash-register" size={24} color="#7c3aed" />
              </View>
              <Text style={styles.quickMenuText}>SPP / Biaya</Text>
            </TouchableOpacity>
          </View>
        </Surface>

        {/* Pengumuman Terbaru */}
        <Text variant="titleMedium" style={styles.sectionTitle}>
          Pengumuman Terbaru
        </Text>
        {stats.pengumuman.map((item) => (
          <Card key={item.id} style={styles.announcementCard}>
            <Card.Content>
              <View style={styles.announcementHeader}>
                <Text style={styles.announcementTitle}>{item.judul}</Text>
                <Badge style={{ backgroundColor: '#0f5132' }}>{item.tanggal}</Badge>
              </View>
              <Divider style={{ marginVertical: 8 }} />
              <Text style={styles.announcementBody}>{item.isi}</Text>
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
  headerSurface: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
  },
  profileRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  profileInfo: {
    flex: 1,
    marginLeft: 12,
  },
  userName: {
    color: '#0f172a',
    fontWeight: 'bold',
  },
  userRole: {
    color: '#64748b',
  },
  notificationBtn: {
    padding: 8,
    backgroundColor: '#f1f5f9',
    borderRadius: 20,
  },
  bannerCard: {
    backgroundColor: '#0f5132',
    borderRadius: 16,
    marginBottom: 20,
  },
  bannerContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 8,
  },
  bannerTextCol: {
    flex: 1,
    marginRight: 12,
  },
  arabicGreeting: {
    color: '#ffffff',
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  bannerSubtext: {
    color: '#a7f3d0',
    fontSize: 12,
  },
  sectionTitle: {
    color: '#0f172a',
    fontWeight: 'bold',
    marginBottom: 12,
    marginTop: 4,
  },
  statsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 8,
    marginBottom: 20,
  },
  statCard: {
    flex: 1,
    backgroundColor: '#ffffff',
    borderRadius: 12,
  },
  statCardInner: {
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 8,
  },
  statNumber: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#0f172a',
    marginTop: 4,
  },
  statLabel: {
    fontSize: 10,
    color: '#64748b',
    textAlign: 'center',
    marginTop: 2,
  },
  quickMenuSurface: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  quickMenuRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  quickMenuItem: {
    alignItems: 'center',
    flex: 1,
  },
  iconCircle: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 6,
  },
  quickMenuText: {
    fontSize: 11,
    color: '#334155',
    fontWeight: '500',
  },
  announcementCard: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    marginBottom: 12,
  },
  announcementHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  announcementTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#0f172a',
    flex: 1,
    marginRight: 8,
  },
  announcementBody: {
    fontSize: 12,
    color: '#475569',
    lineHeight: 18,
  },
});
