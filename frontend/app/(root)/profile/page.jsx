"use client";

import Loader from "@components/Loader";
import { PersonOutline } from "@mui/icons-material";
import { useAuth } from '@/contexts/AuthContext';
import React, { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import { apiClient } from '@/lib/api-client';
import toast from 'react-hot-toast';
import { avatarUrl } from '@/lib/avatar';

const Profile = () => {
  const { user, updateUser } = useAuth();

  const [loading, setLoading] = useState(true);
  
  const {
    register,
    watch,
    setValue,
    reset,
    handleSubmit,
    formState: { errors },
  } = useForm();

  useEffect(() => {
    if (user) {
      reset({
        username: user?.username || user?.name,
        bio: user?.bio || "",
      });
    }
    setLoading(false);
  }, [user]);



  const updateProfile = async (data) => {
    setLoading(true);
    try {
      const updated = await apiClient.updateUser({ name: data.username, bio: data.bio });
      updateUser(updated);
      toast.success("Profile updated!");
    } catch (error) {
      toast.error("Failed to update profile");
    } finally {
      setLoading(false);
    }
  };

  return loading ? (
    <Loader />
  ) : (
    <div className="profile-page">
      <h1 className="text-heading3-bold">Edit Your Profile</h1>
  <form className="edit-profile" onSubmit={handleSubmit(updateProfile)}>
        <div className="input">
          <input
            {...register("username", {
              required: "Username is required",
              validate: (value) => {
                if (value.length < 3) {
                  return "Username must be at least 3 characters";
                }
              },
            })}
            type="text"
            placeholder="Username"
            className="input-field"
          />
          <PersonOutline sx={{ color: "#737373" }} />
        </div>
        {errors?.username && (
          <p className="text-red-500">{errors.username.message}</p>
        )}

        <div>
          <div className="input">
            <input
              {...register("bio")}
              type="text"
              placeholder="Bio"
              className="input-field"
            />
          </div>
        </div>

        <button className="btn" type="submit">
          Save Changes
        </button>
      </form>
    </div>
  );
};

export default Profile;
