import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  View,
  ScrollView,
  SafeAreaView,
  TouchableOpacity,
  Alert,
} from 'react-native';
import {
  Text,
  Card,
  Avatar,
  Button,
  Badge,
  Surface,
  Divider,
  List,
} from 'react-native-paper';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { mobileApiService } from '../services/mobileApiService';
import { useAuthStore } from '../stores/authStore';

export default function ProfilScreen() {
  const setToken = useAuthStore((state) => state.setToken);
  const [profile, setProfile] = useState({
    name: 'Ahmad Zaki Al-Faruq',
    email: 'zaki.alfaruq@sims-sekolah.sch.id',
    phone: '0812-3456-7890',
    role: 'Wali Murid / Orang Tua',
    unit: 'SDIT & SMPIT Islam Terpadu',
    nisn: '0051234567',
  });

  useEffect(() => {
    mobileApiService.getProfile().then((data) => {
      if (data) {
        setProfile((prev) => ({
          ...prev,
          name: data.name || prev.name,
          email: data.email || prev.email,
          role: data.role || prev.role,
        }));
      }
    });
  }, []);

  const handleLogout = () => {
    Alert.alert('Keluar Aplikasi', 'Apakah Anda yakin ingin keluar dari akun ini?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Keluar',
        style: 'destructive',
        onPress: () => setToken(null),
      },
    ]);
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView style={styles.container} contentContainerStyle={styles.contentContainer}>
        {/* User Identity Card */}
        <Surface style={styles.profileHeader} elevation={2}>
          <Avatar.Text
            size={72}
            label="AZ"
            style={{ backgroundColor: '#0f5132' }}
            color="#ffffff"
          />
          <Text variant="titleLarge" style={styles.nameText}>
            {profile.name}
          </Text>
          <Text variant="bodySmall" style={styles.emailText}>
            {profile.email}
          </Text>

          <View style={styles.badgeRow}>
            <Badge style={styles.roleBadge}>{profile.role}</Badge>
            <Badge style={styles.unitBadge}>{profile.unit}</Badge>
          </View>
        </Surface>

        {/* Informasi Personal Card */}
        <Card style={styles.sectionCard}>
          <Card.Content>
            <Text variant="titleMedium" style={styles.sectionTitle}>
              Informasi Akun
            </Text>
            <Divider style={{ marginVertical: 8 }} />

            <List.Item
              title="No. Telepon / WhatsApp"
              description={profile.phone}
              left={(props) => <List.Icon {...props} icon="phone" color="#0f5132" />}
            />
            <List.Item
              title="Nomor Induk / NISN Siswa"
              description={profile.nisn}
              left={(props) => <List.Icon {...props} icon="card-account-details" color="#0f5132" />}
            />
            <List.Item
              title="Unit Sekolah Terdaftar"
              description={profile.unit}
              left={(props) => <List.Icon {...props} icon="school" color="#0f5132" />}
            />
          </Card.Content>
        </Card>

        {/* Pengaturan Aplikasi */}
        <Card style={styles.sectionCard}>
          <Card.Content>
            <Text variant="titleMedium" style={styles.sectionTitle}>
              Pengaturan & Bantuan
            </Text>
            <Divider style={{ marginVertical: 8 }} />

            <TouchableOpacity style={styles.menuRow}>
              <View style={styles.menuLeft}>
                <MaterialCommunityIcons name="bell-ring-outline" size={22} color="#0f5132" />
                <Text style={styles.menuText}>Notifikasi Sekolah</Text>
              </View>
              <MaterialCommunityIcons name="chevron-right" size={22} color="#94a3b8" />
            </TouchableOpacity>

            <TouchableOpacity style={styles.menuRow}>
              <View style={styles.menuLeft}>
                <MaterialCommunityIcons name="lock-reset" size={22} color="#0f5132" />
                <Text style={styles.menuText}>Ubah Kata Sandi</Text>
              </View>
              <MaterialCommunityIcons name="chevron-right" size={22} color="#94a3b8" />
            </TouchableOpacity>

            <TouchableOpacity style={styles.menuRow}>
              <View style={styles.menuLeft}>
                <MaterialCommunityIcons name="help-circle-outline" size={22} color="#0f5132" />
                <Text style={styles.menuText}>Pusat Bantuan & Layanan</Text>
              </View>
              <MaterialCommunityIcons name="chevron-right" size={22} color="#94a3b8" />
            </TouchableOpacity>
          </Card.Content>
        </Card>

        {/* Logout Button */}
        <Button
          mode="outlined"
          onPress={handleLogout}
          icon="logout"
          textColor="#ef4444"
          style={styles.logoutBtn}
        >
          Keluar dari Akun
        </Button>

        <Text style={styles.versionText}>SIMS Mobile App v1.0.0 (Build 2026.07)</Text>
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
  profileHeader: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    marginBottom: 16,
  },
  nameText: {
    fontWeight: 'bold',
    color: '#0f172a',
    marginTop: 12,
  },
  emailText: {
    color: '#64748b',
    marginTop: 2,
  },
  badgeRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 12,
  },
  roleBadge: {
    backgroundColor: '#0f5132',
    paddingHorizontal: 10,
  },
  unitBadge: {
    backgroundColor: '#10b981',
    paddingHorizontal: 10,
  },
  sectionCard: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    marginBottom: 16,
  },
  sectionTitle: {
    fontWeight: 'bold',
    color: '#0f172a',
  },
  menuRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  menuLeft: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  menuText: {
    fontSize: 13,
    color: '#334155',
    marginLeft: 12,
    fontWeight: '500',
  },
  logoutBtn: {
    borderColor: '#ef4444',
    borderRadius: 10,
    marginTop: 8,
    marginBottom: 16,
  },
  versionText: {
    textAlign: 'center',
    fontSize: 11,
    color: '#94a3b8',
  },
});
