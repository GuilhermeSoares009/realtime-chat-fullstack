"use client";

import { useAuth } from '@/contexts/AuthContext';
import { apiClient } from '@/lib/api-client';
import toast from 'react-hot-toast';
import { useEffect, useState } from "react";
import Loader from "./Loader";
import { CheckCircle, RadioButtonUnchecked } from "@mui/icons-material";
import { useRouter } from "next/navigation";
import { avatarUrl } from '@/lib/avatar';

const Contacts = () => {
  const [loading, setLoading] = useState(true);
  const [contacts, setContacts] = useState([]);
  const [search, setSearch] = useState("");

  const { user: currentUser } = useAuth();

  const getContacts = async () => {
    try {
      const data = search === "" ? await apiClient.getUsers() : await apiClient.searchUsers(search);

      const normalized = (data || []).map(u => ({
        id: u.id ?? u._id,
        username: u.username ?? u.name,
        profileImage: u.profileImage ?? u.avatar,
      })).filter(contact => contact.id !== currentUser.id);
      setContacts(normalized);
      setLoading(false);
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    if (currentUser) getContacts();
  }, [currentUser, search]);

  const [selectedContact, setSelectedContact] = useState(null);

  const handleSelect = (contact) => {
    setSelectedContact(contact);
  };

  const router = useRouter();

  const [loadingCreate, setLoadingCreate] = useState(false);

  const createChat = async () => {
    if (!selectedContact) {
      toast.error("Please select a contact");
      return;
    }
    setLoadingCreate(true);
    try {
      const chat = await apiClient.createDirectChat(selectedContact.id);
      setSelectedContact(null);
      setSearch("");
      router.push(`/chats/${chat.id ?? chat._id}`);
    } catch (error) {
      toast.error("Failed to create chat");
    } finally {
      setLoadingCreate(false);
    }
  };

  return loading ? (
    <Loader />
  ) : (
    <div className="create-chat-container">
      <input
        placeholder="Search contact..."
        className="input-search"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      <div className="contact-bar">
        <div className="contact-list">
          <p className="text-body-bold">Select Contact</p>

          <div className="flex flex-col flex-1 gap-5 overflow-y-scroll custom-scrollbar">
            {contacts.map((user, index) => (
              <div
                key={user.id || index}
                className={`contact ${selectedContact?.id === user.id ? 'bg-purple-1 rounded-lg' : ''}`}
                onClick={() => handleSelect(user)}
              >
                {selectedContact?.id === user.id ? (
                  <CheckCircle sx={{ color: "red" }} />
                ) : (
                  <RadioButtonUnchecked />
                )}
                <img
                  src={avatarUrl(user, 64)}
                  alt="profile"
                  className="profilePhoto"
                />
                <p className="text-base-bold">{user.username}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="create-chat">
          <button
            className="btn"
            onClick={createChat}
            disabled={!selectedContact || loadingCreate}
          >
            {loadingCreate ? 'Creating...' : 'START CHAT'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default Contacts;