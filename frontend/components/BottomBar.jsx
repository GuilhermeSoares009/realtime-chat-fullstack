"use client"

import { Logout } from "@mui/icons-material";
import { useAuth } from '@/contexts/AuthContext';
import Link from "next/link";
import { usePathname } from "next/navigation";
import { avatarUrl } from '@/lib/avatar';

const BottomBar = () => {
  const pathname = usePathname();

  const { user, logout } = useAuth();

  const handleLogout = async () => {
    logout();
  };

  return (
    <div className="bottom-bar">
      <Link
        href="/chats"
        className={`${
          pathname === "/chats" ? "text-red-1" : ""
        } text-heading4-bold`}
      >
        Chats
      </Link>
      <Link
        href="/contacts"
        className={`${
          pathname === "/contacts" ? "text-red-1" : ""
        } text-heading4-bold`}
      >
        Contacts
      </Link>

      <Logout
        sx={{ color: "#737373", cursor: "pointer" }}
        onClick={handleLogout}
      />

      <Link href="/profile">
        <img
          src={avatarUrl(user, 80)}
          alt="profile"
          className="profilePhoto"
        />
      </Link>
    </div>
  );
};

export default BottomBar;
